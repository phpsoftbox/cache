<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache;

use DateInterval;
use PhpSoftBox\Cache\Configurator\CacheStoreFactoryInterface;
use PhpSoftBox\Cache\Contracts\CacheServiceInterface;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PhpSoftBox\Cache\Psr6\CacheItemPool;
use PhpSoftBox\Cache\Support\CacheKey;
use PhpSoftBox\Cache\Support\CachePruneOptions;
use PhpSoftBox\Cache\Support\CachePruneResult;

/**
 * Основной сервис Cache для внедрения через DI.
 */
final class Cache implements CacheServiceInterface
{
    /**
     * Контекстный namespace по имени стора (например, текущий арендатор).
     *
     * @var array<string, string>
     */
    private array $contextNamespaces = [];

    /**
     * Сторы по имени и контекстному namespace: повторный вызов store()/pool() отдаёт тот же объект
     * (важно для отложенных записей PSR-6).
     *
     * @var array<string, array<string, CacheStore>>
     */
    private array $stores = [];

    public function __construct(
        private readonly CacheStoreFactoryInterface $storeFactory,
        private readonly string $defaultStore = 'default',
    ) {
    }

    /**
     * Store по имени с учётом контекстного namespace.
     *
     * Не сохраняйте результат надолго: после смены контекстного namespace store нужно запросить заново.
     */
    public function store(?string $store = null): CacheStore
    {
        $store ??= $this->defaultStore;

        $context = $this->contextNamespaces[$store] ?? '';

        if (!isset($this->stores[$store][$context])) {
            $base = $this->storeFactory->store($store);

            $this->stores[$store][$context] = $context === '' ? $base : $base->withNamespace($context);
        }

        return $this->stores[$store][$context];
    }

    /**
     * Низкоуровневый доступ к PSR-6 pool стора (например для сторонних библиотек).
     */
    public function pool(?string $store = null): CacheItemPool
    {
        return $this->store($store)->psr6();
    }

    /**
     * Низкоуровневый доступ к PSR-16 cache стора (например для сторонних библиотек).
     */
    public function simple(?string $store = null): SimpleCache
    {
        return $this->store($store)->psr16();
    }

    public function storeWithNamespace(string $namespace, ?string $store = null): CacheStore
    {
        return $this->store($store)->withNamespace($namespace);
    }

    public function contextNamespace(?string $store = null): string
    {
        return $this->contextNamespaces[$store ?? $this->defaultStore] ?? '';
    }

    public function setContextNamespace(string $namespace, ?string $store = null): void
    {
        CacheKey::assertValidNamespace($namespace);

        $store ??= $this->defaultStore;
        if ($namespace === '') {
            unset($this->contextNamespaces[$store]);

            return;
        }

        $this->contextNamespaces[$store] = $namespace;
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
        // очищает namespace стора по умолчанию с учётом контекстного namespace
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

    public function prune(?CachePruneOptions $options = null, ?string $store = null): CachePruneResult
    {
        return $this->store($store)->prune($options);
    }
}
