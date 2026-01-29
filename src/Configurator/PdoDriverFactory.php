<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Configurator;

use InvalidArgumentException;
use PDO;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Driver\Pdo\PdoCacheSchema;
use PhpSoftBox\Cache\Driver\Pdo\PdoDriverEnum;
use PhpSoftBox\Cache\Driver\Pdo\PdoDriverOptions;
use PhpSoftBox\Cache\Driver\PdoDriver;

/**
 * DI-friendly фабрика PDO драйвера.
 *
 * Варианты использования:
 *
 * 1) В DI вы создаёте PDO и передаёте его в фабрику.
 * 2) В non-DI режиме вы создаёте PDO вручную и создаёте PdoDriver напрямую.
 */
final readonly class PdoDriverFactory implements DriverFactoryInterface
{
    public function __construct(
        private PDO $pdo,
        private ?PdoDriverOptions $defaultOptions = null,
    ) {
    }

    public function supports(string $driver): bool
    {
        return $driver === 'pdo';
    }

    public function create(CacheConfig $config): DriverInterface
    {
        $schema = new PdoCacheSchema(
            table: (string) ($config->options['table'] ?? ($this->defaultOptions?->schema->table ?? 'psb_cache')),
            keyColumn: (string) ($config->options['key_column'] ?? ($this->defaultOptions?->schema->keyColumn ?? 'cache_key')),
            valueColumn: (string) ($config->options['value_column'] ?? ($this->defaultOptions?->schema->valueColumn ?? 'cache_value')),
            expirationDatetimeColumn: (string) ($config->options['expiration_datetime_column'] ?? ($this->defaultOptions?->schema->expirationDatetimeColumn ?? 'expiration_datetime')),
            createdDatetimeColumn: (string) ($config->options['created_datetime_column'] ?? ($this->defaultOptions?->schema->createdDatetimeColumn ?? 'created_datetime')),
        );

        $driver     = (string) ($config->options['driver'] ?? $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        $driverEnum = match ($driver) {
            'sqlite' => PdoDriverEnum::SQLITE,
            'mysql'  => PdoDriverEnum::MYSQL,
            'pgsql'  => PdoDriverEnum::PGSQL,
            default  => throw new InvalidArgumentException('Unsupported PDO driver: ' . $driver),
        };

        $autoCreateTable = (bool) ($config->options['auto_create_table'] ?? ($this->defaultOptions?->autoCreateTable ?? true));

        return new PdoDriver(
            pdo: $this->pdo,
            options: new PdoDriverOptions(schema: $schema, driver: $driverEnum, autoCreateTable: $autoCreateTable),
        );
    }
}
