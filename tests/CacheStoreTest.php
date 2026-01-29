<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\CacheStore;
use PhpSoftBox\Cache\Configurator\CacheBuilder;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PhpSoftBox\Cache\Psr6\CacheItemPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheStore::class)]
final class CacheStoreTest extends TestCase
{
    /**
     * Проверяет, что store() даёт удобный PSR-16 API и умеет отдавать PSR-16/PSR-6 по запросу.
     */
    #[Test]
    public function storeProvidesUnifiedApi(): void
    {
        $config = [
            'default' => 'default',
            'stores'  => [
                'default' => [
                    'driver'    => 'array',
                    'namespace' => 'app',
                ],
            ],
        ];

        $cache = CacheBuilder::fromConfig($config);
        $store = $cache->store();

        self::assertInstanceOf(CacheStore::class, $store);
        self::assertTrue($store->set('foo', 'bar'));
        self::assertSame('bar', $store->get('foo'));

        self::assertInstanceOf(SimpleCache::class, $store->psr16());
        self::assertInstanceOf(CacheItemPool::class, $store->psr6());
    }
}
