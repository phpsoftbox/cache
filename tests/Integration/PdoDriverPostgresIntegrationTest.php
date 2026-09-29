<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests\Integration;

use PhpSoftBox\Cache\Driver\Pdo\PdoCacheSchema;
use PhpSoftBox\Cache\Driver\Pdo\PdoDriverOptions;
use PhpSoftBox\Cache\Driver\PdoDriver;
use PhpSoftBox\Cache\Tests\Fixture\PrivateStateValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function sleep;

#[CoversClass(PdoDriver::class)]
#[CoversMethod(PdoDriver::class, 'set')]
#[CoversMethod(PdoDriver::class, 'fetch')]
#[CoversMethod(PdoDriver::class, 'clear')]
final class PdoDriverPostgresIntegrationTest extends TestCase
{
    /**
     * Проверяет, что PDO драйвер работает с Postgres: умеет создавать таблицу, писать/читать и учитывать TTL.
     */
    #[Test]
    public function worksWithPostgres(): void
    {
        try {
            $db = IntegrationDatabases::postgresPdo();
        } catch (Throwable $e) {
            self::markTestSkipped('Postgres is not available: ' . $e->getMessage());
        }

        $pdo = $db['pdo'];

        $schema = new PdoCacheSchema(table: 'psb_cache_it_pg');

        // чистим таблицу если уже существует
        $pdo->exec('DROP TABLE IF EXISTS "psb_cache_it_pg"');

        $driver = new PdoDriver(
            pdo: $pdo,
            options: new PdoDriverOptions(
                schema: $schema,
                driver: $db['driver'],
                autoCreateTable: true,
            ),
        );

        self::assertTrue($driver->set('k1', ['a' => 1], 2));
        self::assertSame(['a' => 1], $driver->get('k1'));

        sleep(3);
        self::assertNull($driver->get('k1'));

        // cleanup
        $pdo->exec('DROP TABLE IF EXISTS "psb_cache_it_pg"');
    }

    /**
     * Проверяет, что на Postgres (TEXT не принимает NUL-байты) сохраняются объекты с private/protected
     * свойствами и бинарные строки.
     *
     * @see PdoDriver::set()
     * @see PdoDriver::fetch()
     */
    #[Test]
    public function storesObjectsWithPrivatePropertiesAndBinaryStrings(): void
    {
        try {
            $db = IntegrationDatabases::postgresPdo();
        } catch (Throwable $e) {
            self::markTestSkipped('Postgres is not available: ' . $e->getMessage());
        }

        $pdo = $db['pdo'];
        $pdo->exec('DROP TABLE IF EXISTS "psb_cache_it_pg_binary"');

        $driver = new PdoDriver(
            pdo: $pdo,
            options: new PdoDriverOptions(
                schema: new PdoCacheSchema(table: 'psb_cache_it_pg_binary'),
                driver: $db['driver'],
                autoCreateTable: true,
            ),
        );

        self::assertTrue($driver->set('object', new PrivateStateValue('secret')));
        self::assertTrue($driver->set('binary', "\0\xff\xfe binary"));

        $object = $driver->get('object');
        self::assertInstanceOf(PrivateStateValue::class, $object);
        self::assertSame('secret', $object->secret());
        self::assertSame("\0\xff\xfe binary", $driver->get('binary'));

        $pdo->exec('DROP TABLE IF EXISTS "psb_cache_it_pg_binary"');
    }
}
