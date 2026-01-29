<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache;

use DateInterval;
use PhpSoftBox\Cache\Configurator\CacheStoreFactoryInterface;
use PhpSoftBox\Cache\Contracts\CacheServiceInterface;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PhpSoftBox\Cache\Psr6\CacheItemPool;

/**
 * Основной сервис Cache для внедрения через DI.
 */
final class Cache implements CacheServiceInterface
{
    /**
     * @var array<string, CacheStore>
     */
    private array $stores = [];

    /**
     * @var array<string, CacheItemPool>
     */
    private array $pools = [];

    /**
     * @var array<string, SimpleCache>
     */
    private array $simples = [];

    public function __construct(
        private readonly CacheStoreFactoryInterface $storeFactory,
        private readonly string $defaultStore = 'default',
    ) {
    }

    public function store(?string $store = null): CacheStore
    {
        $store ??= $this->defaultStore;

        return $this->stores[$store] ??= $this->storeFactory->store($store);
    }

    /**
     * Низкоуровневый доступ к PSR-6 pool (например для сторонних библиотек).
     */
    public function pool(?string $store = null): CacheItemPool
    {
        $store ??= $this->defaultStore;

        return $this->pools[$store] ??= $this->storeFactory->pool($store);
    }

    /**
     * Низкоуровневый доступ к PSR-16 cache (например для сторонних библиотек).
     */
    public function simple(?string $store = null): SimpleCache
    {
        $store ??= $this->defaultStore;

        return $this->simples[$store] ??= $this->storeFactory->simple($store);
    }

    public function storeWithNamespace(string $namespace, ?string $store = null): CacheStore
    {
        return $this->store($store)->withNamespace($namespace);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store()->get($key, $default);
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        return $this->store()->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->store()->delete($key);
    }

    public function clear(): bool
    {
        // Важно: clear() очищает весь store (и namespace стора). Feature-namespace тут не учитывается.
        return $this->store()->clear();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->store()->getMultiple($keys, $default);
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        return $this->store()->setMultiple($values, $ttl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return $this->store()->deleteMultiple($keys);
    }

    public function has(string $key): bool
    {
        return $this->store()->has($key);
    }
}
