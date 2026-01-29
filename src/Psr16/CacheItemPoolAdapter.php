<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Psr16;

use DateInterval;
use PhpSoftBox\Cache\Psr6\CacheItem;
use PhpSoftBox\Cache\Psr6\InvalidKeyException as Psr6InvalidKeyException;
use PhpSoftBox\Cache\Psr6\RuntimeCacheException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

use function max;
use function time;

/**
 * Адаптер PSR-16 -> PSR-6.
 *
 * Нужен, когда наружу нужно отдать CacheItemPoolInterface, но внутри проект использует SimpleCache.
 */
final class CacheItemPoolAdapter implements CacheItemPoolInterface
{
    /**
     * @var array<string, CacheItem>
     */
    private array $deferred = [];

    private SimpleCache $simple;

    private int|DateInterval|null $defaultTtl;

    public function __construct(SimpleCache $simple, int|DateInterval|null $defaultTtl = null)
    {
        $this->simple     = $simple;
        $this->defaultTtl = $defaultTtl;
    }

    public function getItem(string $key): CacheItemInterface
    {
        try {
            $value = $this->simple->get($key, default: UniqueMiss::value());
            if ($value === UniqueMiss::value()) {
                return new CacheItem($key, null, false);
            }

            return new CacheItem($key, $value, true);
        } catch (InvalidKeyException $e) {
            throw new Psr6InvalidKeyException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function getItems(array $keys = []): iterable
    {
        $items = [];

        foreach ($keys as $key) {
            $items[(string) $key] = $this->getItem((string) $key);
        }

        return $items;
    }

    public function hasItem(string $key): bool
    {
        try {
            return $this->simple->has($key);
        } catch (InvalidKeyException $e) {
            throw new Psr6InvalidKeyException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function clear(): bool
    {
        $this->deferred = [];

        return $this->simple->clear();
    }

    public function deleteItem(string $key): bool
    {
        unset($this->deferred[$key]);

        try {
            return $this->simple->delete($key);
        } catch (InvalidKeyException $e) {
            throw new Psr6InvalidKeyException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->deferred[(string) $key]);
        }

        try {
            return $this->simple->deleteMultiple($keys);
        } catch (InvalidKeyException $e) {
            throw new Psr6InvalidKeyException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function save(CacheItemInterface $item): bool
    {
        try {
            $ttl = $this->ttlForItem($item);

            return $this->simple->set($item->getKey(), $item->get(), $ttl ?? $this->defaultTtl);
        } catch (InvalidKeyException $e) {
            throw new Psr6InvalidKeyException($e->getMessage(), (int) $e->getCode(), $e);
        } catch (Throwable $e) {
            throw new RuntimeCacheException('Failed to save cache item.', 0, $e);
        }
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$item instanceof CacheItem) {
            $this->deferred[$item->getKey()] = new CacheItem($item->getKey(), $item->get(), $item->isHit());

            return true;
        }

        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    public function commit(): bool
    {
        $ok = true;

        foreach ($this->deferred as $item) {
            $ok = $this->save($item) && $ok;
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

        return null;
    }
}
