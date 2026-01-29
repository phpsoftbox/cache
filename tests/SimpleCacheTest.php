<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Driver\ArrayDriver;
use PhpSoftBox\Cache\Psr16\InvalidKeyException;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SimpleCache::class)]
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
}
