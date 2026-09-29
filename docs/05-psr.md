# PSR-16 / PSR-6 и интеграции

## Рекомендуемый подход

В прикладном коде используйте `CacheStore`:

```php
$store = $cache->store('default');
$store->set('foo', 'bar');
```

## PSR-16

Если библиотека/код ожидает `Psr\SimpleCache\CacheInterface`:

```php
$psr16 = $cache->store('default')->psr16();
```

Можно получить тот же объект напрямую через `Cache::simple('default')`.

## PSR-6

Если библиотека/код ожидает `Psr\Cache\CacheItemPoolInterface`:

```php
$psr6 = $cache->store('default')->psr6();
```

Эквивалент на сервисе: `Cache::pool('default')`.

PSR-6 и PSR-16 одного стора работают поверх одного драйвера и одного namespace: значение, записанное через
`psr16()`, читается через `psr6()` и наоборот. Закешированный `null` — это hit (`isHit() === true`).

Ключи PSR-6, как и PSR-16, не могут содержать `{}()/\@:` — иначе `InvalidArgumentException`.
`expiresAt()` в прошлом (или `expiresAfter()` с отрицательным значением) при `save()` удаляет запись.

### saveDeferred/commit

PSR-6 поддерживает отложенную запись:

```php
$item = $psr6->getItem('k');
$item->set('v');
$psr6->saveDeferred($item);
$psr6->commit();
```

> Примечание про `clear()`: `CacheItemPool::clear()`, `SimpleCache::clear()`, `CacheStore::clear()` и
> `Cache::clear()` очищают только namespace стора (включая вложенные). Store без namespace очищает всё
> хранилище драйвера — для Redis это `FLUSHDB`, для Memcached — весь сервер.
> См. [Очистка](02-quick-start.md#очистка).
