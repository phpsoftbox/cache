<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Configurator;

use PhpSoftBox\Cache\Cache;

/**
 * Сборка компонента Cache из массива конфигурации (для использования без DI).
 *
 * Встроенные драйверы: `array`, `file` и `chain` (уровни chain — встроенные и переданные драйверы).
 * Остальные драйверы (redis, memcached, pdo) подключаются через $driverFactories.
 */
final class CacheBuilder
{
    /**
     * @param array{
     *   default?: string,
     *   stores?: array<string, array{driver?: string, namespace?: string, default_ttl?: mixed, options?: array<string, mixed>}>
     * } $config
     * @param list<DriverFactoryInterface> $driverFactories дополнительные фабрики драйверов
     */
    public static function fromConfig(array $config, array $driverFactories = []): Cache
    {
        $factory      = self::storeFactoryFromConfig($config, $driverFactories);
        $defaultStore = (string) ($config['default'] ?? 'default');

        return new Cache(storeFactory: $factory, defaultStore: $defaultStore);
    }

    /**
     * @param list<DriverFactoryInterface> $driverFactories дополнительные фабрики драйверов
     */
    public static function storeFactoryFromConfig(array $config, array $driverFactories = []): CacheStoreFactory
    {
        /** @var array<string, array<string, mixed>> $storesConfig */
        $storesConfig = $config['stores'] ?? [];

        $stores = [];
        foreach ($storesConfig as $name => $store) {
            $stores[(string) $name] = new CacheConfig(
                driver: (string) ($store['driver'] ?? 'array'),
                namespace: (string) ($store['namespace'] ?? ''),
                defaultTtl: $store['default_ttl'] ?? null,
                options: (array) ($store['options'] ?? []),
            );
        }

        if (!isset($stores['default'])) {
            $stores['default'] = new CacheConfig(driver: 'array');
        }

        $factories   = [...$driverFactories, new BuiltInDriverFactory()];
        $factories[] = new ChainDriverFactory($factories);

        return new CacheStoreFactory(
            stores: $stores,
            driverFactories: $factories,
        );
    }
}
