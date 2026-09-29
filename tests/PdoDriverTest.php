<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PDO;
use PhpSoftBox\Cache\Driver\Pdo\PdoCacheSchema;
use PhpSoftBox\Cache\Driver\Pdo\PdoDriverEnum;
use PhpSoftBox\Cache\Driver\Pdo\PdoDriverOptions;
use PhpSoftBox\Cache\Driver\PdoDriver;
use PhpSoftBox\Cache\Tests\Fixture\PrivateStateValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function serialize;
use function sleep;
use function time;

#[CoversClass(PdoDriver::class)]
#[CoversMethod(PdoDriver::class, 'set')]
#[CoversMethod(PdoDriver::class, 'fetch')]
#[CoversMethod(PdoDriver::class, 'fetchWithExpiration')]
#[CoversMethod(PdoDriver::class, 'clear')]
final class PdoDriverTest extends TestCase
{
    /**
     * Проверяет, что PDO driver работает на sqlite::memory и учитывает TTL.
     *
     * @see PdoDriver::set()
     * @see PdoDriver::get()
     */
    #[Test]
    public function worksWithSqliteMemory(): void
    {
        [, $driver] = $this->driver();

        self::assertTrue($driver->set('a', 1, 1));
        self::assertSame(1, $driver->get('a'));

        // протухание
        sleep(2);
        self::assertNull($driver->get('a'));
    }

    /**
     * Проверяет, что TTL 0 удаляет существующую запись (PSR-16).
     *
     * @see PdoDriver::set()
     */
    #[Test]
    public function zeroTtlDeletesExistingKey(): void
    {
        [, $driver] = $this->driver();
        $driver->set('a', 'v');

        self::assertTrue($driver->set('a', 'new', 0));

        self::assertFalse($driver->has('a'));
    }

    /**
     * Проверяет, что объекты с private/protected свойствами и бинарные строки сохраняются без потерь.
     *
     * @see PdoDriver::set()
     * @see PdoDriver::fetch()
     */
    #[Test]
    public function storesObjectsWithPrivatePropertiesAndBinaryStrings(): void
    {
        [, $driver] = $this->driver();

        self::assertTrue($driver->set('object', new PrivateStateValue("se\0cret")));
        self::assertTrue($driver->set('binary', "\0\xff\xfe binary"));

        $object = $driver->get('object');
        self::assertInstanceOf(PrivateStateValue::class, $object);
        self::assertSame("se\0cret", $object->secret());
        self::assertSame('protected', $object->label());
        self::assertSame("\0\xff\xfe binary", $driver->get('binary'));
    }

    /**
     * Проверяет, что запись в формате до 1.0 (serialize без base64) считается промахом, а не мусорным значением.
     *
     * @see PdoDriver::fetch()
     */
    #[Test]
    public function legacySerializedValueIsMiss(): void
    {
        [$pdo, $driver] = $this->driver();

        $pdo->prepare(
            '
                INSERT INTO cache_test (cache_key, cache_value, expiration_datetime, created_datetime)
                VALUES (?, ?, NULL, ?)
            ',
        )
            ->execute(['legacy', serialize('old'), 'now']);

        self::assertFalse($driver->fetch('legacy')['hit']);
    }

    /**
     * Проверяет, что fetchWithExpiration отдаёт момент истечения записи.
     *
     * @see PdoDriver::fetchWithExpiration()
     */
    #[Test]
    public function fetchWithExpirationReturnsExpiresAt(): void
    {
        [, $driver] = $this->driver();
        $driver->set('a', 'v', 100);

        $expiresAt = $driver->fetchWithExpiration('a')['expiresAt'];

        self::assertGreaterThanOrEqual(time() + 99, $expiresAt);
        self::assertLessThanOrEqual(time() + 100, $expiresAt);
    }

    /**
     * Проверяет, что clear() с namespace удаляет только ключи этого namespace; `_` в namespace не работает как
     * wildcard LIKE.
     *
     * @see PdoDriver::clear()
     */
    #[Test]
    public function clearWithNamespaceRemovesOnlyNamespaceKeys(): void
    {
        [, $driver] = $this->driver();
        $driver->set('app_1:a', 1);
        $driver->set('app_1:sub:b', 2);
        $driver->set('appx1:c', 3);
        $driver->set('plain', 4);

        self::assertTrue($driver->clear('app_1'));

        self::assertFalse($driver->has('app_1:a'));
        self::assertFalse($driver->has('app_1:sub:b'));
        self::assertTrue($driver->has('appx1:c'));
        self::assertTrue($driver->has('plain'));
    }

    /**
     * @return array{PDO, PdoDriver}
     */
    private function driver(): array
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $driver = new PdoDriver(
            pdo: $pdo,
            options: new PdoDriverOptions(
                schema: new PdoCacheSchema(table: 'cache_test'),
                driver: PdoDriverEnum::SQLITE,
                autoCreateTable: true,
            ),
        );

        return [$pdo, $driver];
    }
}
