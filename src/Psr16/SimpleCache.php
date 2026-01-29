<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Psr16;

use DateInterval;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use Psr\SimpleCache\CacheInterface;

use function array_values;
use function preg_match;

final readonly class SimpleCache implements CacheInterface
{
    public function __construct(
        private DriverInterface $driver,
        private string $namespace = '',
        private int|DateInterval|null $defaultTtl = null,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->key($key);

        $f = $this->driver->fetch($key);

        return $f['hit'] ? $f['value'] : $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $key = $this->key($key);

        return $this->driver->set($key, $value, $ttl ?? $this->defaultTtl);
    }

    public function delete(string $key): bool
    {
        $key = $this->key($key);

        return $this->driver->delete($key);
    }

    public function clear(): bool
    {
        return $this->driver->clear();
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
            $k                      = (string) $k;
            $mapped[$this->key($k)] = $v;
        }

        return $this->driver->setMultiple($mapped, $ttl ?? $this->defaultTtl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $mapped = [];
        foreach ($keys as $k) {
            $k        = (string) $k;
            $mapped[] = $this->key($k);
        }

        return $this->driver->deleteMultiple($mapped);
    }

    public function has(string $key): bool
    {
        $key = $this->key($key);

        return $this->driver->has($key);
    }

    private function key(string $key): string
    {
        $this->assertValidKey($key);

        return $this->namespace === '' ? $key : $this->namespace . ':' . $key;
    }

    private function assertValidKey(string $key): void
    {
        // PSR-16: ключ должен быть строкой и не содержать {}()/\@:
        if ($key === '') {
            throw new InvalidKeyException('Cache key must not be empty.');
        }

        if (preg_match('/[{}()\/@:]/', $key) === 1) {
            throw new InvalidKeyException('Cache key contains reserved characters.');
        }
    }
}
