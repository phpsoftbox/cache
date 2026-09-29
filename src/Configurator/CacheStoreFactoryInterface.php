<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Configurator;

use PhpSoftBox\Cache\CacheStore;

interface CacheStoreFactoryInterface
{
    /**
     * Store по имени. PSR-16 и PSR-6 стора работают поверх одного драйвера.
     */
    public function store(string $store = 'default'): CacheStore;
}
