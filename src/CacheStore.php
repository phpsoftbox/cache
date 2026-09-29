<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache;

use DateInterval;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PhpSoftBox\Cache\Psr6\CacheItemPool;
use PhpSoftBox\Cache\Support\CachePruneOptions;
use PhpSoftBox\Cache\Support\CachePruneResult;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Высокоуровневый объект "store".
 *
 * PSR-16 API (get/set/etc.) и PSR-6 pool поверх одного драйвера и одного namespace. Объект неизменяемый:
 * withNamespace() возвращает новый store.
 */
final readonly class CacheStore
{
    public function __construct(
        private SimpleCache $simple,
        private CacheItemPool $pool,
    ) {
    }

    /**
     * Store с вложенным namespace: для стора с namespace `app` вызов `withNamespace('login')` даёт ключи
     * `app:login:<key>`. clear() такого стора очищает только `app:login`.
     */
    public function withNamespace(string $namespace): self
    {
        return new self(
            simple: $this->simple->withNamespace($namespace),
            pool: $this->pool->withNamespace($namespace),
        );
    }

    /**
     * Полный namespace стора (сегменты через `:`), пустая строка — без namespace.
     */
    public function namespace(): string
    {
        return $this->simple->namespace();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->simple->get($key, $default);
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        return $this->simple->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->simple->delete($key);
    }

    /**
     * Очищает namespace стора (включая вложенные). Store без namespace очищает всё хранилище драйвера:
     * для Redis — FLUSHDB, для Memcached — весь сервер.
     */
    public function clear(): bool
    {
        return $this->simple->clear();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->simple->getMultiple($keys, $default);
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        return $this->simple->setMultiple($values, $ttl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return $this->simple->deleteMultiple($keys);
    }

    public function has(string $key): bool
    {
        return $this->simple->has($key);
    }

    public function prune(?CachePruneOptions $options = null): CachePruneResult
    {
        return $this->simple->prune($options);
    }

    /**
     * Явный доступ к PSR-16 для интеграций.
     */
    public function psr16(): SimpleCache
    {
        return $this->simple;
    }

    /**
     * Явный доступ к PSR-6 для интеграций (тот же драйвер и namespace, что у PSR-16).
     */
    public function psr6(): CacheItemPool
    {
        return $this->pool;
    }
}
