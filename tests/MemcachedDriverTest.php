<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use Memcached;
use PhpSoftBox\Cache\Driver\MemcachedDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_values;
use function extension_loaded;
use function is_array;

#[CoversClass(MemcachedDriver::class)]
final class MemcachedDriverTest extends TestCase
{
    /**
     * Проверяет базовый set/get на Memcached.
     */
    #[Test]
    public function worksIfMemcachedExtensionIsAvailable(): void
    {
        if (!extension_loaded('memcached')) {
            self::markTestSkipped('ext-memcached is not installed.');
        }

        $mem = new Memcached();

        $mem->addServer('memcached', 11211);

        // Проверяем доступность сервиса
        $version = $mem->getVersion();
        if (!is_array($version) || $version === [] || array_values($version)[0] === false) {
            self::markTestSkipped('Memcached server is not available.');
        }

        $mem->flush();

        $driver = new MemcachedDriver($mem);

        self::assertTrue($driver->set('a', 1, 10));
        self::assertSame(1, $driver->get('a'));
    }
}
