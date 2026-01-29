<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Configurator;

use Memcached;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Driver\MemcachedDriver;

/**
 * DI-friendly фабрика Memcached драйвера (ext-memcached).
 */
final readonly class MemcachedDriverFactory implements DriverFactoryInterface
{
    public function __construct(
        private Memcached $memcached,
    ) {
    }

    public function supports(string $driver): bool
    {
        return $driver === 'memcached';
    }

    public function create(CacheConfig $config): DriverInterface
    {
        return new MemcachedDriver($this->memcached);
    }
}
