<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Configurator;

use PhpSoftBox\Cache\Cache;

/**
 * Сборка компонента Cache из массива конфигурации (для использования без DI).
 */
final class CacheBuilder
{
    /**
     * @param array{
     *   default?: string,
     *   stores?: array<string, array{driver?: string, namespace?: string, default_ttl?: mixed, options?: array<string, mixed>}>
     * } $config
     */
    public static function fromConfig(array $config): Cache
    {
        $factory      = self::storeFactoryFromConfig($config);
        $defaultStore = (string) ($config['default'] ?? 'default');

        return new Cache(storeFactory: $factory, defaultStore: $defaultStore);
    }

    public static function storeFactoryFromConfig(array $config): CacheStoreFactory
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

        return new CacheStoreFactory(
            stores: $stores,
            driverFactories: [new BuiltInDriverFactory()],
        );
    }
}
