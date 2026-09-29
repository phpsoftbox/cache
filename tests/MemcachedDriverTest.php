<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use Memcached;
use PhpSoftBox\Cache\Driver\MemcachedDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_values;
use function extension_loaded;
use function is_array;

#[CoversClass(MemcachedDriver::class)]
#[CoversMethod(MemcachedDriver::class, 'set')]
#[CoversMethod(MemcachedDriver::class, 'fetch')]
#[CoversMethod(MemcachedDriver::class, 'fetchMultiple')]
#[CoversMethod(MemcachedDriver::class, 'clear')]
final class MemcachedDriverTest extends TestCase
{
    /**
     * Проверяет базовый set/get на Memcached.
     *
     * @see MemcachedDriver::set()
     * @see MemcachedDriver::get()
     */
    #[Test]
    public function setAndGetWork(): void
    {
        $driver = new MemcachedDriver($this->memcached());

        self::assertTrue($driver->set('a', 1, 10));
        self::assertSame(1, $driver->get('a'));
    }

    /**
     * Проверяет, что закешированный null — hit.
     *
     * @see MemcachedDriver::fetch()
     */
    #[Test]
    public function nullValueIsHit(): void
    {
        $driver = new MemcachedDriver($this->memcached());

        $driver->set('a', null);

        self::assertSame(['hit' => true, 'value' => null], $driver->fetch('a'));
    }

    /**
     * Проверяет, что TTL больше 30 дней не превращается в «истекло сразу».
     *
     * @see MemcachedDriver::set()
     */
    #[Test]
    public function ttlLongerThanThirtyDaysIsStored(): void
    {
        $driver = new MemcachedDriver($this->memcached());

        self::assertTrue($driver->set('long', 'v', 31 * 86400));

        self::assertSame('v', $driver->get('long'));
    }

    /**
     * Проверяет, что TTL 0 удаляет существующую запись, а не делает её вечной.
     *
     * @see MemcachedDriver::set()
     */
    #[Test]
    public function zeroTtlDeletesExistingKey(): void
    {
        $driver = new MemcachedDriver($this->memcached());

        $driver->set('a', 'v');

        self::assertTrue($driver->set('a', 'new', 0));

        self::assertFalse($driver->has('a'));
    }

    /**
     * Проверяет, что ошибка соединения — промах, а не hit со значением null.
     *
     * @see MemcachedDriver::fetch()
     */
    #[Test]
    public function connectionErrorIsMiss(): void
    {
        if (!extension_loaded('memcached')) {
            self::markTestSkipped('ext-memcached is not installed.');
        }

        // порт, на котором никто не слушает
        $memcached = new Memcached();

        $memcached->addServer('127.0.0.1', 1);

        $driver = new MemcachedDriver($memcached);

        self::assertSame(['hit' => false, 'value' => null], $driver->fetch('a'));
    }

    /**
     * Проверяет, что clear() с namespace делает недоступными только ключи namespace (включая вложенные).
     *
     * @see MemcachedDriver::clear()
     * @see MemcachedDriver::fetchMultiple()
     */
    #[Test]
    public function clearWithNamespaceKeepsOtherKeys(): void
    {
        $driver = new MemcachedDriver($this->memcached());

        $driver->setMultiple(['app:a' => 1, 'app:sub:b' => 2, 'other:c' => 3, 'plain' => 4]);

        self::assertTrue($driver->clear('app'));

        self::assertSame(
            ['app:a' => null, 'app:sub:b' => null, 'other:c' => 3, 'plain' => 4],
            $driver->getMultiple(['app:a', 'app:sub:b', 'other:c', 'plain']),
        );
    }

    /**
     * Проверяет, что clear() вложенного namespace не трогает родительский.
     *
     * @see MemcachedDriver::clear()
     */
    #[Test]
    public function clearNestedNamespaceKeepsParentKeys(): void
    {
        $driver = new MemcachedDriver($this->memcached());

        $driver->set('app:a', 1);
        $driver->set('app:sub:b', 2);

        self::assertTrue($driver->clear('app:sub'));

        self::assertSame(1, $driver->get('app:a'));
        self::assertFalse($driver->has('app:sub:b'));
    }

    private function memcached(): Memcached
    {
        if (!extension_loaded('memcached')) {
            self::markTestSkipped('ext-memcached is not installed.');
        }

        $memcached = new Memcached();

        $memcached->addServer('memcached', 11211);

        // Проверяем доступность сервиса
        $version = $memcached->getVersion();
        if (!is_array($version) || $version === [] || array_values($version)[0] === false) {
            self::markTestSkipped('Memcached server is not available.');
        }

        $memcached->flush();

        return $memcached;
    }
}
