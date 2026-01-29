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
  - в коде представлен `PhpSoftBox\Cache\CacheStore`
- **Cache** — главный сервис (через DI), который умеет отдавать `store()` по имени

Смотрите оглавление: [docs/index.md](index.md)
