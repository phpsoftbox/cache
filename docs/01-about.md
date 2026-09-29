# About

`phpsoftbox/cache` — компонент кеширования для PhpSoftBox.

Цели:

- удобный **единый объект для внедрения**: `PhpSoftBox\Cache\Cache`
- поддержка нескольких сторах (stores): `default`, `files`, `redis` и т.д.
- расширяемые драйверы (через `DriverFactoryInterface`)
- два стандарта PSR:
  - PSR-16 — удобно в прикладном коде
  - PSR-6 — нужно для интеграций/advanced сценариев

## Термины

- **Driver** — низкоуровневая реализация хранения (array, file, redis, memcached, pdo, chain)
- **Store** — именованный экземпляр кеша (настройки + namespace + ttl)
  - в коде представлен `PhpSoftBox\Cache\CacheStore` (неизменяемый объект)
  - PSR-16 (`psr16()`) и PSR-6 (`psr6()`) стора работают поверх **одного** драйвера
- **Namespace** — префикс ключей стора: сегменты через `:` (`app`, `app:login`); ключ в драйвере — `app:login:key`
- **Cache** — главный сервис (через DI), который умеет отдавать `store()` по имени

Смотрите оглавление: [docs/index.md](index.md)
