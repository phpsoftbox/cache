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

### saveDeferred/commit

PSR-6 поддерживает отложенную запись:

```php
$item = $psr6->getItem('k');
$item->set('v');
$psr6->saveDeferred($item);
$psr6->commit();
```

> Примечание про `clear()`: вызов `Cache::clear()` или `CacheStore::clear()` очищает
> весь store с учётом namespace стора, но не feature-namespace, добавленного
> через `withNamespace()`. Для точечного удаления используйте `delete()`/`deleteMultiple()`.
