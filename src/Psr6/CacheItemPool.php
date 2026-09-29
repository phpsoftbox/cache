<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Psr6;

use DateInterval;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Support\CacheKey;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

use function array_values;
use function time;

/**
 * PSR-6 поверх драйвера. Ключи и namespace — те же, что у SimpleCache того же стора, поэтому записанное через
 * PSR-16 видно через PSR-6 и наоборот.
 */
final class CacheItemPool implements CacheItemPoolInterface
{
    /**
     * @var array<string, CacheItem>
     */
    private array $deferred = [];

    /**
     * @param string $namespace сегменты через `:`, например `app` или `app:tenant-1`
     */
    public function __construct(
        private readonly DriverInterface $driver,
        private readonly string $namespace = '',
        private readonly int|DateInterval|null $defaultTtl = null,
    ) {
        CacheKey::assertValidNamespace($namespace);
    }

    /**
     * Новый pool с вложенным namespace (отложенные записи не переносятся).
     */
    public function withNamespace(string $namespace): self
    {
        return new self(
            driver: $this->driver,
            namespace: CacheKey::nestNamespace($this->namespace, $namespace),
            defaultTtl: $this->defaultTtl,
        );
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function getItem(string $key): CacheItemInterface
    {
        $f = $this->driver->fetch($this->key($key));

        return new CacheItem($key, $f['hit'] ? $f['value'] : null, $f['hit']);
    }

    public function getItems(array $keys = []): iterable
    {
        $mapped = [];
        foreach ($keys as $k) {
            $k          = (string) $k;
            $mapped[$k] = $this->key($k);
        }

        $fetched = $this->driver->fetchMultiple(array_values($mapped));

        $items = [];
        foreach ($mapped as $original => $realKey) {
            $f                = $fetched[$realKey] ?? ['hit' => false, 'value' => null];
            $items[$original] = new CacheItem((string) $original, $f['hit'] ? $f['value'] : null, $f['hit']);
        }

        return $items;
    }

    public function hasItem(string $key): bool
    {
        return $this->driver->has($this->key($key));
    }

    public function clear(): bool
    {
        $this->deferred = [];

        return $this->driver->clear($this->namespace);
    }

    public function deleteItem(string $key): bool
    {
        $realKey = $this->key($key);
        unset($this->deferred[$key]);

        return $this->driver->delete($realKey);
    }

    public function deleteItems(array $keys): bool
    {
        $mapped = [];
        foreach ($keys as $k) {
            $k        = (string) $k;
            $mapped[] = $this->key($k);
            unset($this->deferred[$k]);
        }

        return $this->driver->deleteMultiple($mapped);
    }

    public function save(CacheItemInterface $item): bool
    {
        $realKey = $this->key($item->getKey());

        try {
            return $this->driver->set($realKey, $item->get(), $this->ttlForItem($item));
        } catch (CacheException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeCacheException('Failed to save cache item.', 0, $e);
        }
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->key($item->getKey());

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

        foreach ($this->deferred as $item) {
            $ok = $this->save($item) && $ok;
        }

        $this->deferred = [];

        return $ok;
    }

    /**
     * TTL записи: срок из expiresAt/expiresAfter (момент в прошлом даёт 0 — драйвер удалит запись)
     * или TTL стора по умолчанию.
     */
    private function ttlForItem(CacheItemInterface $item): int|DateInterval|null
    {
        if ($item instanceof CacheItem && $item->getExpiresAt() !== null) {
            return $item->getExpiresAt() - time();
        }

        return $this->defaultTtl;
    }

    /**
     * @throws InvalidKeyException
     */
    private function key(string $key): string
    {
        // PSR-6: ключ не пустой и без зарезервированных символов {}()/\@:
        if (!CacheKey::isValid($key)) {
            throw new InvalidKeyException('Cache key "' . $key . '" is empty or contains reserved characters {}()/\@:.');
        }

        return CacheKey::join($this->namespace, $key);
    }
}
