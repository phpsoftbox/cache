<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\CacheStore;
use PhpSoftBox\Cache\Configurator\CacheBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheStore::class)]
#[CoversMethod(CacheStore::class, 'withNamespace')]
final class CacheNamespaceTest extends TestCase
{
    /**
     * Проверяет, что CacheStore::withNamespace() добавляет префикс к ключам.
     *
     * @see CacheStore::withNamespace()
     */
    #[Test]
    public function withNamespacePrefixesKeys(): void
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

        $featureStore = $cache->storeWithNamespace('login-attempts-user-1');

        self::assertTrue($featureStore->set('count', 5));

        // исходный store не видит ключ без namespace
        self::assertNull($cache->store()->get('count'));

        // а namespaced store видит
        self::assertSame(5, $featureStore->get('count'));
    }
}
