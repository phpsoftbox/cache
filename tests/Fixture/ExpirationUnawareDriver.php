<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests\Fixture;

use DateInterval;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Driver\ArrayDriver;

/**
 * Драйвер без ExpirationAwareDriverInterface (как Memcached): оставшийся TTL записи неизвестен.
 */
final class ExpirationUnawareDriver implements DriverInterface
{
    private ArrayDriver $inner;

    public function __construct()
    {
        $this->inner = new ArrayDriver();
    }

    public static function isSupported(): bool
    {
        return true;
    }

    public function fetch(string $key): array
    {
        return $this->inner->fetch($key);
    }

    public function get(string $key): mixed
    {
        return $this->inner->get($key);
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        return $this->inner->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->inner->delete($key);
    }

    public function clear(string $namespace = ''): bool
    {
        return $this->inner->clear($namespace);
    }

    public function fetchMultiple(iterable $keys): array
    {
        return $this->inner->fetchMultiple($keys);
    }

    public function getMultiple(iterable $keys): array
    {
        return $this->inner->getMultiple($keys);
    }

    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        return $this->inner->setMultiple($values, $ttl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return $this->inner->deleteMultiple($keys);
    }

    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }
}
