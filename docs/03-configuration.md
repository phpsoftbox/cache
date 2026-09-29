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

- `driver` — строковый идентификатор драйвера (`array`, `file`, `chain`, ...)
- `namespace` — префикс ключей (будет добавлен как `namespace:key`); сегменты через `:`, без `{}()/\@`.
  Ограничивает `clear()` этим namespace — задавайте его всегда, когда хранилище общее
- `default_ttl` — TTL по умолчанию (секунды или `DateInterval`)
- `options` — массив driver-specific опций

`CacheBuilder` знает встроенные драйверы `array`, `file` и `chain`. Остальные подключаются вторым
аргументом:

```php
$cache = CacheBuilder::fromConfig($config, [
    new \PhpSoftBox\Cache\Configurator\RedisDriverFactory($redis),
]);
```

## Очистка (`clear()`) по драйверам

| Драйвер   | `clear()` стора с namespace                                    | `clear()` стора без namespace |
|-----------|----------------------------------------------------------------|-------------------------------|
| array     | удаляет ключи `namespace:*`                                     | всё хранилище процесса        |
| file      | читает файлы каталога и удаляет файлы namespace                | все `*.cache` каталога        |
| pdo       | `DELETE ... WHERE key LIKE 'namespace:%'`                      | вся таблица                   |
| redis     | `SCAN MATCH namespace:*` + `UNLINK`, остальные ключи базы целы | `FLUSHDB` текущей базы        |
| memcached | смена версии namespace (см. ниже)                              | `flush` всего сервера         |
| chain     | `clear()` каждого уровня                                        | `clear()` каждого уровня      |

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
`serialize()`, каждый ключ хранится отдельным файлом `*.cache` (внутри — ключ, срок жизни и значение),
каталог создаётся автоматически. `clear()` с namespace читает все файлы каталога: для больших каталогов
это медленная операция. Файлы старого формата (без ключа) при `clear()` с namespace удаляются.

## Драйвер: chain

`chain` — это цепочка драйверов (L1 -> L2 -> L3 ...).

- чтение идёт сверху вниз
- при hit на нижнем уровне значение прогревает верхние уровни **с оставшимся сроком жизни записи**;
  если нижний уровень срок не сообщает (Memcached), — с `warmup_ttl`
- запись, удаление и `clear()` идут во все уровни

Опции:

- `drivers` — непустой список уровней. Уровень — имя драйвера (`'array'`) или массив
  `['driver' => 'file', 'options' => [...]]`, опции передаются вложенному драйверу. Вложенный `chain` не
  поддерживается
- `warmup_ttl` — TTL прогрева (сек), когда срок записи на нижнем уровне неизвестен; по умолчанию 60

Пример (без DI):

```php
$cache = \PhpSoftBox\Cache\Configurator\CacheBuilder::fromConfig([
  'default' => 'default',
  'stores' => [
    'default' => [
      'driver' => 'chain',
      'namespace' => 'app',
      'options' => [
        'drivers' => [
          'array', // L1
          ['driver' => 'file', 'options' => ['directory' => __DIR__ . '/var/cache']], // L2
        ],
        'warmup_ttl' => 60,
      ],
    ],
  ],
]);
```

В DI-режиме chain собирается через `ChainDriverFactory`, которому передаётся список
`DriverFactoryInterface` для уровней (например, `BuiltInDriverFactory` и `RedisDriverFactory`), а сам
`ChainDriverFactory` добавляется в `driverFactories` фабрики сторов.

Учтите: верхний уровень `array` — память процесса. Удаление и `clear()` в одном процессе не видны L1 других
процессов, пока не истечёт срок прогретой записи.

## Драйвер: pdo

`pdo` — хранение кеша в таблице БД через `\PDO`.

Настройки (через `options`):

- `table` — имя таблицы
- `driver` — тип SQL-движка (`sqlite`/`mysql`/`pgsql`) для корректного upsert/quoting
- `key_column` — колонка primary key
- `value_column` — колонка с данными: `base64(serialize(value))` в `TEXT` (безопасно для бинарных строк и
  объектов с private/protected свойствами, в том числе на Postgres)
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

Записи, сохранённые версиями до 1.0 (`serialize()` без base64), читаются как промах и удаляются при чтении.

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

Особенности:

- `clear()` стора с namespace удаляет только ключи `namespace:*` через `SCAN` + `UNLINK` (не блокирует
  сервер, но проходит весь keyspace базы); сессии, очереди и rate-limit в той же базе не трогаются
- `clear()` стора **без** namespace — `FLUSHDB` текущей базы
- TTL `0`/отрицательный удаляет ключ; `setMultiple()` с TTL пишет ключи через `SETEX` в pipeline

## Драйвер: memcached

`memcached` — кеш через **ext-memcached**.

Особенности:

- TTL больше 30 дней передаётся как абсолютный timestamp (`time() + ttl`), поэтому долгие TTL работают
- hit — только при `RES_SUCCESS`: ошибка соединения — промах, а не `null`
- Memcached не умеет удалять по префиксу, поэтому namespace **версионируется**: у каждого уровня namespace
  есть случайная версия в служебном ключе `@ns:<namespace>`, реальный ключ — `app@<версия>:key`. `clear()`
  с namespace меняет версию: старые записи становятся недоступны и вытесняются по TTL/LRU. Каждая операция с
  ключом в namespace делает дополнительный запрос версий (один `getMulti`)
- `clear()` стора **без** namespace — `flush` всего сервера

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

## Свои драйверы

Драйвер реализует `PhpSoftBox\Cache\Contracts\DriverInterface`:

- ключи приходят с namespace (`app:login:key`); `clear(string $namespace)` должен удалить (или сделать
  недоступными) все ключи `namespace:*`, пустой namespace — всё хранилище
- TTL `<= 0` в `set()`/`setMultiple()` удаляет ключи; удаление отсутствующего ключа возвращает `true`
- опционально `PrunableDriverInterface` (команда `cache:prune`) и `ExpirationAwareDriverInterface`
  (`fetchWithExpiration()` — срок жизни записи для точного прогрева в `chain`)

## Примечания

Тесты Redis/Memcached/MariaDB/Postgres пропускаются, если нет расширений/сервисов.
