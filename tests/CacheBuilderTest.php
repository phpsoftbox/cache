<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Cache;
use PhpSoftBox\Cache\Configurator\BuiltInDriverFactory;
use PhpSoftBox\Cache\Configurator\CacheBuilder;
use PhpSoftBox\Cache\Configurator\CacheStoreFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function random_bytes;
use function sys_get_temp_dir;

#[CoversClass(CacheBuilder::class)]
final class CacheBuilderTest extends TestCase
{
    /**
     * Проверяет, что CacheBuilder собирает Cache и использует встроенные драйверы.
     */
    #[Test]
    public function builderCreatesCache(): void
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
        self::assertInstanceOf(Cache::class, $cache);

        self::assertSame('bar', $cache->store()->set('foo', 'bar') ? $cache->store()->get('foo') : null);
    }

    /**
     * Проверяет, что storeFactoryFromConfig возвращает CacheStoreFactory с BuiltInDriverFactory.
     */
    #[Test]
    public function builderCreatesStoreFactory(): void
    {
        $config = [
            'stores' => [
                'default' => [
                    'driver'    => 'file',
                    'namespace' => 'app',
                    'options'   => [
                        'directory' => sys_get_temp_dir() . '/phpsoftbox-cache-test-' . bin2hex(random_bytes(6)),
                    ],
                ],
            ],
        ];

        $factory = CacheBuilder::storeFactoryFromConfig($config);
        self::assertInstanceOf(CacheStoreFactory::class, $factory);

        // косвенно: built-in driver factory позволяет создать file-store
        $store = $factory->store('default');
        self::assertTrue($store->set('foo', 'bar'));
        self::assertSame('bar', $store->get('foo'));
    }
}
