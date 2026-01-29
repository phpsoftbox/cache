<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Configurator;

use DateInterval;

/**
 * Конфигурация стора кеша.
 */
final class CacheConfig
{
    public function __construct(
        public readonly string $driver,
        public readonly string $namespace = '',
        public readonly int|DateInterval|null $defaultTtl = null,
        /**
         * @var array<string, mixed>
         */
        public readonly array $options = [],
    ) {
    }
}
