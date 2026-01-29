<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Driver;

use DateInterval;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Support\Ttl;

use function array_key_exists;
use function max;
use function time;

/**
 * In-memory драйвер. Удобно для тестов/локального кеша в рамках одного процесса.
 */
final class ArrayDriver implements DriverInterface
{
    /**
     * @var array<string, array{value: mixed, expirationDatetime: int|null}>
     */
    private array $data = [];

    public static function isSupported(): bool
    {
        return true;
    }

    public function fetch(string $key): array
    {
        if (!array_key_exists($key, $this->data)) {
            return ['hit' => false, 'value' => null];
        }

        $expirationDatetime = $this->data[$key]['expirationDatetime'];
        if ($expirationDatetime !== null && $expirationDatetime < time()) {
            unset($this->data[$key]);

            return ['hit' => false, 'value' => null];
        }

        return ['hit' => true, 'value' => $this->data[$key]['value']];
    }

    public function get(string $key): mixed
    {
        $f = $this->fetch($key);

        return $f['hit'] ? $f['value'] : null;
    }

    public function fetchMultiple(iterable $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $key          = (string) $key;
            $result[$key] = $this->fetch($key);
        }

        return $result;
    }

    public function getMultiple(iterable $keys): array
    {
        $result = [];
        foreach ($this->fetchMultiple($keys) as $key => $f) {
            $result[$key] = $f['hit'] ? $f['value'] : null;
        }

        return $result;
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $ttlSeconds = Ttl::normalizeSeconds($ttl);

        $expirationDatetime = null;
        if ($ttlSeconds !== null) {
            $expirationDatetime = time() + max(0, $ttlSeconds);
        }

        $this->data[$key] = [
            'value'              => $value,
            'expirationDatetime' => $expirationDatetime,
        ];

        return true;
    }

    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->data[$key]);

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
    }

    public function clear(): bool
    {
        $this->data = [];

        return true;
    }

    public function has(string $key): bool
    {
        return $this->fetch($key)['hit'];
    }
}
