<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Configurator;

use PhpSoftBox\Cache\CacheStore;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PhpSoftBox\Cache\Psr6\CacheItemPool;

interface CacheFactoryInterface
{
    public function store(string $store = 'default'): CacheStore;

    public function pool(string $store = 'default'): CacheItemPool;

    public function simple(string $store = 'default'): SimpleCache;
}
