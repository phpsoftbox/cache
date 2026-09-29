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
- *namespace фичи* (например `login-attempts-user-1`) — задаётся в коде: `storeWithNamespace()` или
  `store()->withNamespace()`; возвращается **новый** store, исходный не меняется

Namespace состоит из сегментов через `:`. Сегмент, как и ключ, не может быть пустым и содержать `{}()/\@:`.

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

// Реальный ключ в драйвере: app:login-attempts-user-1:count
```

## Контекстный namespace (арендаторы)

Если весь код, работающий через сервис `Cache` (`$cache->get()`, `$cache->store()`, `$cache->pool()`,
`$cache->simple()`), должен временно работать в отдельном namespace — например, в контексте арендатора, —
задайте контекстный namespace:

```php
$cache->setContextNamespace('tenant-42');          // стор по умолчанию
$cache->setContextNamespace('tenant-42', 'files'); // стор files

$cache->set('foo', 'bar'); // ключ app:tenant-42:foo

$cache->setContextNamespace(''); // снять контекст
```

`contextNamespace(?string $store)` возвращает текущее значение. Объекты `CacheStore`, полученные до смены
контекста, не меняются — запрашивайте store у сервиса заново, а не храните его в свойствах долгоживущих
сервисов.

## Явный доступ к PSR-16 и PSR-6

- `Cache::simple()` — отдаёт `Psr\SimpleCache\CacheInterface` стора (тот же объект, что `store()->psr16()`).
- `Cache::pool()` — отдаёт `Psr\Cache\CacheItemPoolInterface` стора (тот же объект, что `store()->psr6()`).
- PSR-16 и PSR-6 одного стора используют один драйвер: записанное через одно видно через другое.

## TTL

- `null` — TTL стора по умолчанию (`default_ttl`), а если он не задан — без срока жизни.
- `0` или отрицательный TTL — запись удаляется (PSR-16); в PSR-6 то же для `expiresAt()` в прошлом.

### Очистка

`clear()` очищает **только namespace стора**, включая вложенные namespace: `clear()` стора `app` удаляет
`app:*` и `app:login:*`, а `clear()` стора `app:login` — только `app:login:*`. Данные других сторов на том же
драйвере (тот же каталог, таблица, база Redis) не трогаются.

Store **без namespace** очищает всё хранилище драйвера: каталог file-драйвера, таблицу PDO, базу Redis
(`FLUSHDB`) или весь сервер Memcached (`flush`). Для Redis и Memcached, которые обычно делят с сессиями,
очередями и rate-limit, всегда задавайте `namespace`.
