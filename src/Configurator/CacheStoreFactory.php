<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Configurator;

use InvalidArgumentException;
use PhpSoftBox\Cache\CacheStore;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PhpSoftBox\Cache\Psr6\CacheItemPool;

/**
 * Фабрика "store" объектов Cache.
 *
 * Драйвер создаётся один раз на store: PSR-16 и PSR-6 одного стора видят одни и те же данные.
 * В DI-варианте этот класс собирается контейнером.
 */
final class CacheStoreFactory implements CacheStoreFactoryInterface
{
    /**
     * @var array<string, CacheStore>
     */
    private array $instances = [];

    /**
     * @param array<string, CacheConfig> $stores
     * @param list<DriverFactoryInterface> $driverFactories
     */
    public function __construct(
        private readonly array $stores,
        private readonly array $driverFactories = [],
    ) {
    }

    public function store(string $store = 'default'): CacheStore
    {
        if (isset($this->instances[$store])) {
            return $this->instances[$store];
        }

        $config = $this->getConfig($store);
        $driver = $this->createDriver($config);

        return $this->instances[$store] = new CacheStore(
            simple: new SimpleCache(
                driver: $driver,
                namespace: $config->namespace,
                defaultTtl: $config->defaultTtl,
            ),
            pool: new CacheItemPool(
                driver: $driver,
                namespace: $config->namespace,
                defaultTtl: $config->defaultTtl,
            ),
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
