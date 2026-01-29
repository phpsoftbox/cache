<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Configurator;

use InvalidArgumentException;
use PhpSoftBox\Cache\CacheStore;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PhpSoftBox\Cache\Psr6\CacheItemPool;

/**
 * Фабрика "store" объектов Cache (и соответствующих PSR адаптеров/пулов).
 *
 * В DI-варианте этот класс собирается контейнером.
 */
final class CacheStoreFactory implements CacheStoreFactoryInterface
{
    /**
     * @param array<string, CacheConfig> $stores
     * @param list<DriverFactoryInterface> $driverFactories
     */
    public function __construct(
        private readonly array $stores,
        private readonly array $driverFactories = [],
    ) {
    }

    public function pool(string $store = 'default'): CacheItemPool
    {
        $config = $this->getConfig($store);
        $driver = $this->createDriver($config);

        return new CacheItemPool(
            driver: $driver,
            namespace: $config->namespace,
            defaultTtl: $config->defaultTtl,
        );
    }

    public function simple(string $store = 'default'): SimpleCache
    {
        $config = $this->getConfig($store);
        $driver = $this->createDriver($config);

        return new SimpleCache(
            driver: $driver,
            namespace: $config->namespace,
            defaultTtl: $config->defaultTtl,
        );
    }

    public function store(string $store = 'default'): CacheStore
    {
        return new CacheStore(
            simple: $this->simple($store),
            pool: $this->pool($store),
        );
    }

    private function getConfig(string $store): CacheConfig
    {
        $config = $this->stores[$store] ?? null;
        if (!$config instanceof CacheConfig) {
            throw new InvalidArgumentException('Unknown cache store: ' . $store);
        }

        return $config;
    }

    private function createDriver(CacheConfig $config): DriverInterface
    {
        foreach ($this->driverFactories as $factory) {
            if ($factory->supports($config->driver)) {
                return $factory->create($config);
            }
        }

        throw new InvalidArgumentException('Unknown cache driver: ' . $config->driver);
    }
}
