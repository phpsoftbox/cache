<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Cache;
use PhpSoftBox\Cache\Configurator\CacheBuilder;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PhpSoftBox\Cache\Psr6\CacheItemPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheBuilder::class)]
final class CacheFacadeTest extends TestCase
{
    /**
     * Проверяет, что CacheBuilder::fromConfig() создаёт Cache и через PSR-16 всё работает.
     */
    #[Test]
    public function simpleFacadeWorks(): void
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

        $cache  = CacheBuilder::fromConfig($config);
        $simple = $cache->simple();
        self::assertInstanceOf(SimpleCache::class, $simple);

        self::assertTrue($simple->set('foo', 'bar'));
        self::assertSame('bar', $simple->get('foo'));
    }

    /**
     * Проверяет, что CacheBuilder::fromConfig() создаёт Cache и через PSR-6 pool всё работает.
     */
    #[Test]
    public function poolFacadeWorks(): void
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
        $pool  = $cache->pool();
        self::assertInstanceOf(CacheItemPool::class, $pool);

        $item = $pool->getItem('foo');
        $item->set('bar');

        self::assertTrue($pool->save($item));
        self::assertTrue($pool->hasItem('foo'));
        self::assertSame('bar', $pool->getItem('foo')->get());
    }
}
