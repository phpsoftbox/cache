<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Psr16;

use DateInterval;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Contracts\PrunableDriverInterface;
use PhpSoftBox\Cache\Support\CacheKey;
use PhpSoftBox\Cache\Support\CachePruneOptions;
use PhpSoftBox\Cache\Support\CachePruneResult;
use Psr\SimpleCache\CacheInterface;

use function array_values;

/**
 * PSR-16 поверх драйвера.
 *
 * Ключи уходят в драйвер как `namespace:key`. clear() очищает только свой namespace (включая вложенные);
 * без namespace — всё хранилище драйвера.
 */
final readonly class SimpleCache implements CacheInterface
{
    /**
     * @param string $namespace сегменты через `:`, например `app` или `app:tenant-1`
     */
    public function __construct(
        private DriverInterface $driver,
        private string $namespace = '',
        private int|DateInterval|null $defaultTtl = null,
    ) {
        CacheKey::assertValidNamespace($namespace);
    }

    /**
     * Копия с вложенным namespace: `withNamespace('login')` для `app` даёт `app:login`.
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

    public function get(string $key, mixed $default = null): mixed
    {
        $f = $this->driver->fetch($this->key($key));

        return $f['hit'] ? $f['value'] : $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        return $this->driver->set($this->key($key), $value, $ttl ?? $this->defaultTtl);
    }

    public function delete(string $key): bool
    {
        return $this->driver->delete($this->key($key));
    }

    public function clear(): bool
    {
        return $this->driver->clear($this->namespace);
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $mapped = [];
        foreach ($keys as $k) {
            $k          = (string) $k;
            $mapped[$k] = $this->key($k);
        }

        $fetched = $this->driver->fetchMultiple(array_values($mapped));

        $out = [];
        foreach ($mapped as $original => $realKey) {
            $f              = $fetched[$realKey] ?? ['hit' => false, 'value' => null];
            $out[$original] = $f['hit'] ? $f['value'] : $default;
        }

        return $out;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $mapped = [];
        foreach ($values as $k => $v) {
            $mapped[$this->key((string) $k)] = $v;
        }

        return $this->driver->setMultiple($mapped, $ttl ?? $this->defaultTtl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $mapped = [];
        foreach ($keys as $k) {
            $mapped[] = $this->key((string) $k);
        }

        return $this->driver->deleteMultiple($mapped);
    }

    public function has(string $key): bool
    {
        return $this->driver->has($this->key($key));
    }

    public function prune(?CachePruneOptions $options = null): CachePruneResult
    {
        if (!$this->driver instanceof PrunableDriverInterface) {
            return CachePruneResult::unsupported();
        }

        return $this->driver->prune($options);
    }

    /**
     * @throws InvalidKeyException
     */
    private function key(string $key): string
    {
        // PSR-16: ключ не пустой и без зарезервированных символов {}()/\@:
        if ($key === '') {
            throw new InvalidKeyException('Cache key must not be empty.');
        }

        if (!CacheKey::isValid($key)) {
            throw new InvalidKeyException('Cache key "' . $key . '" contains reserved characters {}()/\@:.');
        }

        return CacheKey::join($this->namespace, $key);
    }
}
