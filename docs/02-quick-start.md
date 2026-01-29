# Quick Start

## 1) Без DI (ручная сборка из config)

```php
use PhpSoftBox\Cache\Configurator\CacheBuilder;

$config = [
    'default' => 'default',
    'stores' => [
        'default' => [
            'driver' => 'array',
            'namespace' => 'app',
            'default_ttl' => 60,
        ],
        'files' => [
            'driver' => 'file',
            'namespace' => 'app',
            'options' => [
                'directory' => __DIR__ . '/var/cache',
            ],
        ],
    ],
];

$cache = CacheBuilder::fromConfig($config);

$store = $cache->store();
$store->set('foo', 'bar', 30);

$value = $store->get('foo');
```

## 2) Через DI (идея)

В DI-контейнере вы регистрируете:

- `DriverFactoryInterface[]`
- `CacheStoreFactoryInterface`
- `Cache`

Пример для php-di — см. [docs/04-di.md](04-di.md).

## Namespace / префиксы ключей

Обычно удобно разделять:

- *namespace стора* (например `app`, `api`) — задаётся в конфиге store через `namespace`
- *namespace фичи* (например `login-attempts-user-1`) — задаётся в коде на уровне store: `storeWithNamespace()` или `store()->withNamespace()`

Пример:

```php
$cache = CacheBuilder::fromConfig([
    'default' => 'default',
    'stores' => [
        'default' => [
            'driver' => 'array',
            'namespace' => 'app',
        ],
    ],
]);

$featureStore = $cache->storeWithNamespace('login-attempts-user-1');
$featureStore->set('count', 1);

// Реальный ключ внутри стора будет: app:login-attempts-user-1-count
```

## Явный доступ к PSR-16 и PSR-6

- `Cache::simple()` — отдаёт `Psr\SimpleCache\CacheInterface` (низкоуровневый доступ к store).
- `Cache::pool()` — отдаёт `Psr\Cache\CacheItemPoolInterface` (PSR-6 pool).
- Те же обёртки есть на уровне store: `store()->psr16()` и `store()->psr6()`.

### Очистка

`Cache::clear()` / `CacheStore::clear()` очищают **весь store** (включая namespace стора),
но не учитывают feature-namespace, добавленный через `withNamespace()`. Если нужно очистить
только конкретный feature-namespace, очистите ключи вручную или заведите отдельный store.
