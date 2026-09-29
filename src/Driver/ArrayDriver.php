<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Driver;

use DateInterval;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Contracts\ExpirationAwareDriverInterface;
use PhpSoftBox\Cache\Contracts\PrunableDriverInterface;
use PhpSoftBox\Cache\Support\CacheKey;
use PhpSoftBox\Cache\Support\CachePruneOptions;
use PhpSoftBox\Cache\Support\CachePruneResult;
use PhpSoftBox\Cache\Support\Ttl;

use function array_key_exists;
use function str_starts_with;
use function time;

/**
 * In-memory драйвер. Удобно для тестов/локального кеша в рамках одного процесса.
 */
final class ArrayDriver implements DriverInterface, PrunableDriverInterface, ExpirationAwareDriverInterface
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
        $f = $this->fetchWithExpiration($key);

        return ['hit' => $f['hit'], 'value' => $f['value']];
    }

    public function fetchWithExpiration(string $key): array
    {
        if (!array_key_exists($key, $this->data)) {
            return ['hit' => false, 'value' => null, 'expiresAt' => null];
        }

        $expirationDatetime = $this->data[$key]['expirationDatetime'];
        if ($expirationDatetime !== null && $expirationDatetime <= time()) {
            unset($this->data[$key]);

            return ['hit' => false, 'value' => null, 'expiresAt' => null];
        }

        return ['hit' => true, 'value' => $this->data[$key]['value'], 'expiresAt' => $expirationDatetime];
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
        if (Ttl::isExpired($ttlSeconds)) {
            return $this->delete($key);
        }

        $this->data[$key] = [
            'value'              => $value,
            'expirationDatetime' => $ttlSeconds === null ? null : time() + $ttlSeconds,
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

    public function clear(string $namespace = ''): bool
    {
        if ($namespace === '') {
            $this->data = [];

            return true;
        }

        $prefix = $namespace . CacheKey::SEPARATOR;
        foreach ($this->data as $key => $item) {
            if (str_starts_with((string) $key, $prefix)) {
                unset($this->data[$key]);
            }
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->fetch($key)['hit'];
    }

    public function prune(?CachePruneOptions $options = null): CachePruneResult
    {
        $now    = time();
        $result = CachePruneResult::empty();

        foreach ($this->data as $key => $item) {
            $result = $result->withScanned();

            $expirationDatetime = $item['expirationDatetime'];
            if ($expirationDatetime !== null && $expirationDatetime <= $now) {
                unset($this->data[$key]);
                $result = $result->withExpired();
            }
        }

        return $result;
    }
}
