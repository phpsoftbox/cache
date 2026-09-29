<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Driver\ArrayDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sleep;
use function time;

#[CoversClass(ArrayDriver::class)]
#[CoversMethod(ArrayDriver::class, 'set')]
#[CoversMethod(ArrayDriver::class, 'fetch')]
#[CoversMethod(ArrayDriver::class, 'fetchWithExpiration')]
#[CoversMethod(ArrayDriver::class, 'setMultiple')]
#[CoversMethod(ArrayDriver::class, 'clear')]
final class ArrayDriverTest extends TestCase
{
    /**
     * Проверяет set/get/has/delete.
     *
     * @see ArrayDriver::set()
     * @see ArrayDriver::get()
     * @see ArrayDriver::has()
     * @see ArrayDriver::delete()
     */
    #[Test]
    public function basicOperationsWork(): void
    {
        $d = new ArrayDriver();

        self::assertFalse($d->has('a'));
        self::assertNull($d->get('a'));

        self::assertTrue($d->set('a', 123));
        self::assertTrue($d->has('a'));
        self::assertSame(123, $d->get('a'));

        self::assertTrue($d->delete('a'));
        self::assertFalse($d->has('a'));
    }

    /**
     * Проверяет, что ttl истекает.
     *
     * @see ArrayDriver::fetch()
     */
    #[Test]
    public function ttlExpires(): void
    {
        $d = new ArrayDriver();

        self::assertTrue($d->set('a', 'v', 1));
        self::assertSame('v', $d->get('a'));

        sleep(2);

        self::assertFalse($d->has('a'));
        self::assertNull($d->get('a'));
    }

    /**
     * Проверяет, что TTL 0 удаляет существующую запись (PSR-16).
     *
     * @see ArrayDriver::set()
     */
    #[Test]
    public function zeroTtlDeletesExistingKey(): void
    {
        $d = new ArrayDriver();

        $d->set('a', 'v');

        self::assertTrue($d->set('a', 'new', 0));

        self::assertFalse($d->has('a'));
    }

    /**
     * Проверяет, что отрицательный TTL в setMultiple удаляет записи.
     *
     * @see ArrayDriver::setMultiple()
     */
    #[Test]
    public function negativeTtlInSetMultipleDeletesKeys(): void
    {
        $d = new ArrayDriver();

        $d->set('a', 1);

        self::assertTrue($d->setMultiple(['a' => 2, 'b' => 3], -10));

        self::assertFalse($d->has('a'));
        self::assertFalse($d->has('b'));
    }

    /**
     * Проверяет, что fetchWithExpiration отдаёт момент истечения записи.
     *
     * @see ArrayDriver::fetchWithExpiration()
     */
    #[Test]
    public function fetchWithExpirationReturnsExpiresAt(): void
    {
        $d = new ArrayDriver();

        $d->set('a', 'v', 100);
        $d->set('b', 'v');

        $expiresAt = $d->fetchWithExpiration('a')['expiresAt'];

        self::assertGreaterThanOrEqual(time() + 99, $expiresAt);
        self::assertLessThanOrEqual(time() + 100, $expiresAt);
        self::assertNull($d->fetchWithExpiration('b')['expiresAt']);
    }

    /**
     * Проверяет, что clear() с namespace удаляет только ключи этого namespace (включая вложенные).
     *
     * @see ArrayDriver::clear()
     */
    #[Test]
    public function clearWithNamespaceRemovesOnlyNamespaceKeys(): void
    {
        $d = new ArrayDriver();

        $d->set('app:a', 1);
        $d->set('app:sub:b', 2);
        $d->set('apple:c', 3);
        $d->set('plain', 4);

        self::assertTrue($d->clear('app'));

        self::assertFalse($d->has('app:a'));
        self::assertFalse($d->has('app:sub:b'));
        self::assertTrue($d->has('apple:c'));
        self::assertTrue($d->has('plain'));
    }
}
