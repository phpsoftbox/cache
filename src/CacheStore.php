<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache;

use DateInterval;
use PhpSoftBox\Cache\Psr16\CacheItemPoolAdapter;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PhpSoftBox\Cache\Psr6\CacheItemPool;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\InvalidArgumentException;

use function array_values;
use function is_array;
use function iterator_to_array;

/**
 * Высокоуровневый объект "store".
 *
 * По умолчанию это PSR-16 API (get/set/etc.), а при необходимости можно получить PSR-6 pool.
 */
final class CacheStore
{
    private ?CacheItemPoolAdapter $poolAdapter = null;

    private string $namespace = '';

    public function __construct(
        private readonly SimpleCache $simple,
        private readonly ?CacheItemPool $pool = null,
    ) {
    }

    public function withNamespace(string $namespace): self
    {
        $clone            = clone $this;
        $clone->namespace = $namespace;

        return $clone;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->simple->get($this->prefixKey($key), $default);
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        return $this->simple->set($this->prefixKey($key), $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->simple->delete($this->prefixKey($key));
    }

    public function clear(): bool
    {
        return $this->simple->clear();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $prefixed = [];
        foreach ($keys as $k) {
            $k            = (string) $k;
            $prefixed[$k] = $this->prefixKey($k);
        }

        $values = $this->simple->getMultiple(array_values($prefixed), $default);

        $out       = [];
        $valuesArr = is_array($values) ? $values : iterator_to_array($values);
        foreach ($prefixed as $original => $real) {
            $out[$original] = $valuesArr[$real] ?? $default;
        }

        return $out;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $mapped = [];
        foreach ($values as $k => $v) {
            $mapped[$this->prefixKey((string) $k)] = $v;
        }

        return $this->simple->setMultiple($mapped, $ttl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $mapped = [];
        foreach ($keys as $k) {
            $mapped[] = $this->prefixKey((string) $k);
        }

        return $this->simple->deleteMultiple($mapped);
    }

    public function has(string $key): bool
    {
        return $this->simple->has($this->prefixKey($key));
    }

    private function prefixKey(string $key): string
    {
        if ($this->namespace == '') {
            return $key;
        }

        return $this->namespace . '-' . $key;
    }

    /**
     * Явный доступ к PSR-16 для интеграций.
     */
    public function psr16(): SimpleCache
    {
        return $this->simple;
    }

    /**
     * Явный доступ к PSR-6 для интеграций.
     *
     * Если store создан только как PSR-16, возвращается адаптер.
     */
    public function psr6(): CacheItemPoolInterface
    {
        if ($this->pool !== null) {
            return $this->pool;
        }

        return $this->poolAdapter ??= new CacheItemPoolAdapter($this->simple);
    }
}
