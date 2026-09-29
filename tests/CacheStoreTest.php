<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\CacheStore;
use PhpSoftBox\Cache\Configurator\CacheBuilder;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PhpSoftBox\Cache\Psr6\CacheItemPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheStore::class)]
#[CoversMethod(CacheStore::class, 'withNamespace')]
#[CoversMethod(CacheStore::class, 'clear')]
#[CoversMethod(CacheStore::class, 'psr6')]
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

    /**
     * Проверяет, что withNamespace() не меняет исходный store и что PSR-6 вложенного стора видит его PSR-16 данные.
     *
     * @see CacheStore::withNamespace()
     * @see CacheStore::psr6()
     */
    #[Test]
    public function withNamespaceReturnsIndependentStore(): void
    {
        $store = CacheBuilder::fromConfig(['stores' => ['default' => ['driver' => 'array', 'namespace' => 'app']]])->store();

        $feature = $store->withNamespace('feature');
        $feature->set('a', 1);

        self::assertSame('app', $store->namespace());
        self::assertSame('app:feature', $feature->namespace());
        self::assertFalse($store->has('a'));
        self::assertSame(1, $feature->psr6()->getItem('a')->get());
    }

    /**
     * Проверяет, что clear() стора очищает и вложенные namespace, а clear() вложенного стора — только его.
     *
     * @see CacheStore::clear()
     */
    #[Test]
    public function clearOfNestedStoreKeepsParentData(): void
    {
        $store = CacheBuilder::fromConfig(['stores' => ['default' => ['driver' => 'array', 'namespace' => 'app']]])->store();

        $feature = $store->withNamespace('feature');
        $store->set('a', 1);
        $feature->set('b', 2);

        self::assertTrue($feature->clear());
        self::assertSame(1, $store->get('a'));
        self::assertFalse($feature->has('b'));

        $feature->set('b', 2);
        self::assertTrue($store->clear());
        self::assertFalse($feature->has('b'));
    }
}
