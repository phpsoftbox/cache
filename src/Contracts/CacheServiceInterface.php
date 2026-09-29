<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Contracts;

use PhpSoftBox\Cache\CacheStore;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PhpSoftBox\Cache\Psr6\CacheItemPool;
use PhpSoftBox\Cache\Support\CachePruneOptions;
use PhpSoftBox\Cache\Support\CachePruneResult;
use Psr\SimpleCache\CacheInterface;

/**
 * Расширенный контракт компонента Cache.
 *
 * Подходит для внедрения в код, когда помимо PSR-16 нужны:
 * - работа со store по имени
 * - доступ к PSR-6 pool
 * - доступ к конкретной реализации PSR-16 (SimpleCache)
 * - namespaced store
 * - контекстный namespace (изоляция арендаторов и т.п.)
 */
interface CacheServiceInterface extends CacheInterface
{
    public function store(?string $store = null): CacheStore;

    public function pool(?string $store = null): CacheItemPool;

    public function simple(?string $store = null): SimpleCache;

    public function storeWithNamespace(string $namespace, ?string $store = null): CacheStore;

    /**
     * Текущий контекстный namespace стора ('' — не задан).
     */
    public function contextNamespace(?string $store = null): string;

    /**
     * Задаёт контекстный namespace стора: store(), pool(), simple() и PSR-16 методы сервиса начинают работать
     * в `<namespace стора>:<контекстный namespace>`. Пустая строка снимает контекст.
     */
    public function setContextNamespace(string $namespace, ?string $store = null): void;

    public function prune(?CachePruneOptions $options = null, ?string $store = null): CachePruneResult;
}
