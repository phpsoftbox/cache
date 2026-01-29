<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Configurator\CacheConfig;
use PhpSoftBox\Cache\Configurator\CacheStoreFactory;
use PhpSoftBox\Cache\Configurator\DriverFactoryInterface;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Driver\ArrayDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheStoreFactory::class)]
final class DriverFactoryResolutionTest extends TestCase
{
    /**
     * Проверяет, что CacheStoreFactory выбирает подходящую DriverFactoryInterface по supports().
     */
    #[Test]
    public function resolvesDriverFactoryBySupports(): void
    {
        $stores = [
            'default' => new CacheConfig(driver: 'custom'),
        ];

        $factory = new CacheStoreFactory(
            stores: $stores,
            driverFactories: [
                new class () implements DriverFactoryInterface {
                    public function supports(string $driver): bool
                    {
                        return $driver === 'custom';
                    }

                    public function create(CacheConfig $config): DriverInterface
                    {
                        return new ArrayDriver();
                    }
                },
            ],
        );

        $store = $factory->store('default');
        self::assertTrue($store->set('a', 1));
        self::assertSame(1, $store->get('a'));
    }
}
