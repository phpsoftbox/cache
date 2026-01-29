<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Configurator;

use InvalidArgumentException;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Driver\ArrayDriver;
use PhpSoftBox\Cache\Driver\FileDriver;

use function in_array;
use function sys_get_temp_dir;

/**
 * Встроенная фабрика драйверов (array/file).
 */
final class BuiltInDriverFactory implements DriverFactoryInterface
{
    public function supports(string $driver): bool
    {
        return in_array($driver, ['array', 'file'], true);
    }

    public function create(CacheConfig $config): DriverInterface
    {
        return match ($config->driver) {
            'array' => new ArrayDriver(),
            'file'  => new FileDriver(
                directory: (string) ($config->options['directory'] ?? sys_get_temp_dir() . '/phpsoftbox-cache'),
            ),
            default => throw new InvalidArgumentException('Unsupported driver in BuiltInDriverFactory: ' . $config->driver),
        };
    }
}
