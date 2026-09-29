<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use InvalidArgumentException;
use PhpSoftBox\Cache\Driver\ArrayDriver;
use PhpSoftBox\Cache\Psr16\InvalidKeyException;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SimpleCache::class)]
#[CoversMethod(SimpleCache::class, 'get')]
#[CoversMethod(SimpleCache::class, 'set')]
#[CoversMethod(SimpleCache::class, 'clear')]
#[CoversMethod(SimpleCache::class, 'withNamespace')]
final class SimpleCacheTest extends TestCase
{
    /**
     * Проверяет, что get возвращает default, если значения нет.
     */
    #[Test]
    public function getReturnsDefaultWhenMissing(): void
    {
        $cache = new SimpleCache(new ArrayDriver());

        self::assertSame('def', $cache->get('missing', 'def'));
    }

    /**
     * Проверяет, что namespace добавляет префикс к ключам.
     */
    #[Test]
    public function namespaceIsApplied(): void
    {
        $driver = new ArrayDriver();

        $cache = new SimpleCache($driver, namespace: 'ns');

        self::assertTrue($cache->set('a', 1));
        self::assertTrue($driver->has('ns:a'));
        self::assertSame(1, $cache->get('a'));
    }

    /**
     * Проверяет, что PSR-16 валидирует ключи.
     */
    #[Test]
    public function invalidKeyThrows(): void
    {
        $cache = new SimpleCache(new ArrayDriver());

        $this->expectException(InvalidKeyException::class);
        $cache->get('bad:key');
    }

    /**
     * Проверяет, что обратный слеш — зарезервированный символ PSR-16.
     *
     * @see SimpleCache::get()
     */
    #[Test]
    public function backslashInKeyThrows(): void
    {
        $cache = new SimpleCache(new ArrayDriver());

        $this->expectException(InvalidKeyException::class);

        $cache->get('bad\\key');
    }

    /**
     * Проверяет, что set() с TTL 0 удаляет существующее значение (PSR-16).
     *
     * @see SimpleCache::set()
     */
    #[Test]
    public function zeroTtlDeletesExistingValue(): void
    {
        $cache = new SimpleCache(new ArrayDriver());

        $cache->set('a', 1);

        self::assertTrue($cache->set('a', 2, 0));

        self::assertFalse($cache->has('a'));
    }

    /**
     * Проверяет, что clear() очищает только свой namespace, не трогая другие stores на том же драйвере.
     *
     * @see SimpleCache::clear()
     */
    #[Test]
    public function clearKeepsOtherNamespaces(): void
    {
        $driver = new ArrayDriver();

        $app   = new SimpleCache($driver, namespace: 'app');
        $other = new SimpleCache($driver, namespace: 'other');
        $app->set('a', 1);
        $other->set('a', 2);

        self::assertTrue($app->clear());

        self::assertFalse($app->has('a'));
        self::assertSame(2, $other->get('a'));
    }

    /**
     * Проверяет, что withNamespace() добавляет вложенный сегмент namespace и не меняет исходный объект.
     *
     * @see SimpleCache::withNamespace()
     */
    #[Test]
    public function withNamespaceNestsNamespace(): void
    {
        $driver = new ArrayDriver();

        $cache = new SimpleCache($driver, namespace: 'app');

        $nested = $cache->withNamespace('login');
        $nested->set('a', 1);

        self::assertSame('app', $cache->namespace());
        self::assertSame('app:login', $nested->namespace());
        self::assertTrue($driver->has('app:login:a'));
    }

    /**
     * Проверяет, что namespace с зарезервированными символами отклоняется.
     *
     * @see SimpleCache::__construct()
     */
    #[Test]
    public function invalidNamespaceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SimpleCache(new ArrayDriver(), namespace: 'app/1');
    }
}
