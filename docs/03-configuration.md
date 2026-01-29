# Конфигурация stores и драйверов

Конфигурация используется только для режима "без DI" (через `CacheBuilder`).
В DI-варианте эти данные чаще всего собираются контейнером и/или конфиг-пакетом.

## Формат

```php
$config = [
  'default' => 'default',
  'stores' => [
    'default' => [
      'driver' => 'array',
      'namespace' => 'app',
      'default_ttl' => 60,
      'options' => [ /* driver-specific */ ],
    ],
  ],
];
```

## Поля store

- `driver` — строковый идентификатор драйвера (`array`, `file`, ...)
- `namespace` — префикс ключей (будет добавлен как `namespace:key`)
- `default_ttl` — TTL по умолчанию (секунды или `DateInterval`)
- `options` — массив driver-specific опций

### Пример: file

```php
'files' => [
  'driver' => 'file',
  'namespace' => 'app',
  'options' => [
    'directory' => __DIR__ . '/var/cache',
  ],
],
```

Если не указать `options.directory`, драйвер по умолчанию создаст каталог
`sys_get_temp_dir() . '/phpsoftbox-cache'`. Значения сериализуются через
`serialize()`, каждый ключ хранится отдельным файлом `*.cache`, каталог
создаётся автоматически при первой записи.

## Драйвер: chain

`chain` — это цепочка драйверов (L1 -> L2 -> L3 ...).

- чтение идёт сверху вниз
- при hit на нижнем уровне значение прогревает верхние уровни
- запись идёт во все уровни

Пример (без DI):

```php
$cache = \PhpSoftBox\Cache\Configurator\CacheBuilder::fromConfig([
  'default' => 'default',
  'stores' => [
    'default' => [
      'driver' => 'chain',
      'namespace' => 'app',
      'options' => [
        'stores' => ['array', 'file'],
      ],
    ],
  ],
]);
```

Важно: в DI-режиме chain собирается через `ChainDriverFactory`, которому нужно передать список доступных `DriverFactoryInterface`.

Минимальный пример конфигурации (без DI) с двумя уровнями:

```php
$config = [
  'default' => 'default',
  'stores' => [
    'default' => [
      'driver' => 'chain',
      'namespace' => 'app',
      'options' => [
        'stores' => ['array', 'file'], // L1, L2
      ],
    ],
    'file' => [
      'driver' => 'file',
      'namespace' => 'app',
    ],
  ],
];
```

## Драйвер: pdo

`pdo` — хранение кеша в таблице БД через `\PDO`.

Настройки (через `options`):

- `table` — имя таблицы
- `driver` — тип SQL-движка (`sqlite`/`mysql`/`pgsql`) для корректного upsert/quoting
- `key_column` — колонка primary key
- `value_column` — колонка с данными (serialized)
- `expiration_datetime_column` — колонка с временем жизни (unix timestamp) или NULL
- `created_datetime_column` — колонка с датой/временем создания
- `auto_create_table` — автоматически создавать таблицу (по умолчанию `true`)

Пример (DI):

- в контейнере вы создаёте `\PDO` (с логином/паролем/DSN и любыми `PDO::ATTR_*`)
- затем регистрируете `PdoDriverFactory($pdo)` как один из `DriverFactoryInterface`

Пример (без DI):

```php
$pdo = new \PDO('sqlite::memory:');
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

$driver = new \PhpSoftBox\Cache\Driver\PdoDriver(
    pdo: $pdo,
    options: new \PhpSoftBox\Cache\Driver\Pdo\PdoDriverOptions(
        schema: new \PhpSoftBox\Cache\Driver\Pdo\PdoCacheSchema(
            table: 'psb_cache',
            keyColumn: 'cache_key',
            valueColumn: 'cache_value',
            expirationDatetimeColumn: 'expiration_datetime',
            createdDatetimeColumn: 'created_datetime',
        ),
        autoCreateTable: true,
    ),
);
```

Важно: namespace в PDO драйвере **не нужен** — он применяется снаружи через `SimpleCache` и `CacheStore` (ключи уже приходят в драйвер с префиксом `namespace:key`).

## Драйвер: redis

`redis` — кеш через **ext-redis**.

Рекомендуемый подход — через DI:

- создать `\Redis` (connect/auth/select)
- зарегистрировать `RedisDriverFactory($redis)` как `DriverFactoryInterface`

Пример конфигурации (без DI) для готового клиента Redis:

```php
$redis = new \Redis();
$redis->connect('redis', 6379);
$redis->select(0);

$factory = new \PhpSoftBox\Cache\Configurator\CacheStoreFactory(
  stores: [
    'default' => new \PhpSoftBox\Cache\Configurator\CacheConfig(
      driver: 'redis',
      namespace: 'app',
    ),
  ],
  driverFactories: [
    new \PhpSoftBox\Cache\Configurator\RedisDriverFactory($redis),
  ],
);
```

## Драйвер: memcached

`memcached` — кеш через **ext-memcached**.

Рекомендуемый подход — через DI:

- создать `\Memcached` (addServer/options)
- зарегистрировать `MemcachedDriverFactory($memcached)` как `DriverFactoryInterface`

Минимальный пример (без DI):

```php
$m = new \Memcached();
$m->addServer('memcache', 11211);

$factory = new \PhpSoftBox\Cache\Configurator\CacheStoreFactory(
  stores: [
    'default' => new \PhpSoftBox\Cache\Configurator\CacheConfig(
      driver: 'memcached',
      namespace: 'app',
    ),
  ],
  driverFactories: [
    new \PhpSoftBox\Cache\Configurator\MemcachedDriverFactory($m),
  ],
);
```

## Примечания

Тесты скипаются, если нет расширений/сервисов.
