<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Psr6;

use DateInterval;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

use function array_values;
use function max;
use function time;

final class CacheItemPool implements CacheItemPoolInterface
{
    /**
     * @var array<string, CacheItem>
     */
    private array $deferred = [];

    public function __construct(
        private readonly DriverInterface $driver,
        private readonly string $namespace = '',
        private readonly int|DateInterval|null $defaultTtl = null,
    ) {
    }

    public function getItem(string $key): CacheItemInterface
    {
        $realKey = $this->key($key);

        $value = $this->driver->get($realKey);
        $isHit = $value !== null;

        return new CacheItem($key, $value, $isHit);
    }

    public function getItems(array $keys = []): iterable
    {
        $mapped = [];
        foreach ($keys as $k) {
            $mapped[$k] = $this->key((string) $k);
        }

        $values = $this->driver->getMultiple(array_values($mapped));

        $items = [];
        foreach ($mapped as $original => $realKey) {
            $value            = $values[$realKey] ?? null;
            $items[$original] = new CacheItem((string) $original, $value, $value !== null);
        }

        return $items;
    }

    public function hasItem(string $key): bool
    {
        $realKey = $this->key($key);

        return $this->driver->has($realKey);
    }

    public function clear(): bool
    {
        $this->deferred = [];

        return $this->driver->clear();
    }

    public function deleteItem(string $key): bool
    {
        unset($this->deferred[$key]);

        return $this->driver->delete($this->key($key));
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $k) {
            unset($this->deferred[(string) $k]);
        }

        $mapped = [];
        foreach ($keys as $k) {
            $mapped[] = $this->key((string) $k);
        }

        return $this->driver->deleteMultiple($mapped);
    }

    public function save(CacheItemInterface $item): bool
    {
        try {
            $ttl = $this->ttlForItem($item);

            return $this->driver->set($this->key($item->getKey()), $item->get(), $ttl);
        } catch (CacheException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeCacheException('Failed to save cache item.', 0, $e);
        }
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$item instanceof CacheItem) {
            // на текущем этапе работаем только со своими item
            $this->deferred[$item->getKey()] = new CacheItem($item->getKey(), $item->get(), $item->isHit());

            return true;
        }

        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    public function commit(): bool
    {
        $ok = true;

        foreach ($this->deferred as $key => $item) {
            $ttl = $this->ttlForItem($item);
            $ok  = $this->driver->set($this->key($key), $item->get(), $ttl) && $ok;
        }

        $this->deferred = [];

        return $ok;
    }

    private function ttlForItem(CacheItemInterface $item): int|DateInterval|null
    {
        if ($item instanceof CacheItem && $item->getExpiresAt() !== null) {
            $ttl = $item->getExpiresAt() - time();

            return max(0, $ttl);
        }

        return $this->defaultTtl;
    }

    private function key(string $key): string
    {
        // PSR-6 не требует тех же ограничений что PSR-16, но здесь всё равно не допускаем пустой ключ.
        if ($key === '') {
            throw new InvalidKeyException('Cache key must not be empty.');
        }

        return $this->namespace === '' ? $key : $this->namespace . ':' . $key;
    }
}
