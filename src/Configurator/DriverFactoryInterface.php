<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Configurator;

use PhpSoftBox\Cache\Contracts\DriverInterface;

/**
 * DI-friendly фабрика драйверов.
 *
 * Идея: CacheFactory не знает про конкретные драйверы.
 * Он просто находит DriverFactoryInterface, который поддерживает нужный driver.
 */
interface DriverFactoryInterface
{
    public function supports(string $driver): bool;

    public function create(CacheConfig $config): DriverInterface;
}
