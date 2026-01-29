<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Configurator;

use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Driver\RedisDriver;
use Redis;

/**
 * DI-friendly фабрика Redis драйвера (ext-redis).
 */
final readonly class RedisDriverFactory implements DriverFactoryInterface
{
    public function __construct(
        private Redis $redis,
    ) {
    }

    public function supports(string $driver): bool
    {
        return $driver === 'redis';
    }

    public function create(CacheConfig $config): DriverInterface
    {
        return new RedisDriver($this->redis);
    }
}
