<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PDO;
use PhpSoftBox\Cache\Driver\Pdo\PdoCacheSchema;
use PhpSoftBox\Cache\Driver\Pdo\PdoDriverEnum;
use PhpSoftBox\Cache\Driver\Pdo\PdoDriverOptions;
use PhpSoftBox\Cache\Driver\PdoDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sleep;

#[CoversClass(PdoDriver::class)]
final class PdoDriverTest extends TestCase
{
    /**
     * Проверяет, что PDO driver работает на sqlite::memory и учитывает TTL.
     */
    #[Test]
    public function worksWithSqliteMemory(): void
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

        self::assertTrue($driver->set('a', 1, 1));
        self::assertSame(1, $driver->get('a'));

        // протухание
        sleep(2);
        self::assertNull($driver->get('a'));
    }
}
