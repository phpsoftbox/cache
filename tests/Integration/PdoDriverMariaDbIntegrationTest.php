<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests\Integration;

use PhpSoftBox\Cache\Driver\Pdo\PdoCacheSchema;
use PhpSoftBox\Cache\Driver\Pdo\PdoDriverOptions;
use PhpSoftBox\Cache\Driver\PdoDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function sleep;

#[CoversClass(PdoDriver::class)]
final class PdoDriverMariaDbIntegrationTest extends TestCase
{
    /**
     * Проверяет, что PDO драйвер работает с MariaDB: умеет создавать таблицу, писать/читать и учитывать TTL.
     */
    #[Test]
    public function worksWithMariaDb(): void
    {
        try {
            $db = IntegrationDatabases::mariadbPdo();
        } catch (Throwable $e) {
            self::markTestSkipped('MariaDB is not available: ' . $e->getMessage());
        }

        $pdo = $db['pdo'];

        // изолируемся отдельной таблицей
        $schema = new PdoCacheSchema(table: 'psb_cache_it_mariadb');

        // чистим таблицу если уже существу��т
        $pdo->exec('DROP TABLE IF EXISTS `psb_cache_it_mariadb`');

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
        $pdo->exec('DROP TABLE IF EXISTS `psb_cache_it_mariadb`');
    }
}
