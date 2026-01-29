<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Contracts;

use PhpSoftBox\Cache\CacheStore;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PhpSoftBox\Cache\Psr6\CacheItemPool;
use Psr\SimpleCache\CacheInterface;

/**
 * Расширенный контракт компонента Cache.
 *
 * Подходит для внедрения в код, когда помимо PSR-16 нужны:
 * - работа со store по имени
 * - доступ к PSR-6 pool
 * - доступ к конкретной реализации PSR-16 (SimpleCache)
 * - namespaced store
 */
interface CacheServiceInterface extends CacheInterface
{
    public function store(?string $store = null): CacheStore;

    public function pool(?string $store = null): CacheItemPool;

    public function simple(?string $store = null): SimpleCache;

    public function storeWithNamespace(string $namespace, ?string $store = null): CacheStore;
}
