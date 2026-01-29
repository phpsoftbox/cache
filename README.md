# PhpSoftBox Cache

## About

`phpsoftbox/cache` — компонент кеширования для PhpSoftBox.

Ключевые свойства:

- основной сервис для DI: `PhpSoftBox\Cache\Cache`
- несколько сторов (stores) по имени: `default`, `files`, `redis` и т.д.
- расширяемые драйверы через `DriverFactoryInterface`
- поддержка PSR-16 и PSR-6

## Установка и требования

- PHP **^8.4** (см. `composer.json`)
- Установите пакет: `composer require phpsoftbox/cache`
- Опциональные расширения/сервисы для драйверов: `ext-redis`, `ext-memcached`, `ext-pdo` (доступ к БД для PDO).

## Quick Start

### Без DI

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
    ],
];

$cache = CacheBuilder::fromConfig($config);

$cache->store()->set('foo', 'bar', 30);
```

### Через DI

См. пример для PHP-DI в документации: [docs/04-di.md](docs/04-di.md).

## Оглавление

- [Документация](docs/index.md)
- [About](docs/01-about.md)
- [Quick Start](docs/02-quick-start.md)
- [Конфигурация](docs/03-configuration.md)
- [DI](docs/04-di.md)
- [PSR-16/PSR-6](docs/05-psr.md)
