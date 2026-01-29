<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Driver;

use DateInterval;
use DateTimeImmutable;
use Memcached;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Exception\CacheException;

use function array_key_exists;
use function extension_loaded;
use function is_int;
use function is_string;
use function serialize;
use function strlen;
use function unserialize;

/**
 * Memcached driver (ext-memcached).
 */
final class MemcachedDriver implements DriverInterface
{
    private const int MAX_KEY_LENGTH = 250;

    public static function isSupported(): bool
    {
        return extension_loaded('memcached');
    }

    public function __construct(
        private readonly Memcached $memcached,
    ) {
        if (!self::isSupported()) {
            throw new CacheException('Memcached extension (ext-memcached) is required.');
        }
    }

    public function fetch(string $key): array
    {
        $this->assertKeyLength($key);
        $value = $this->memcached->get($key);
        if ($this->memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            return ['hit' => false, 'value' => null];
        }

        return ['hit' => true, 'value' => $this->unserializeValue($value)];
    }

    public function get(string $key): mixed
    {
        $f = $this->fetch($key);

        return $f['hit'] ? $f['value'] : null;
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $this->assertKeyLength($key);
        $ttlSeconds = $this->normalizeTtlSeconds($ttl);
        $payload    = $this->serializeValue($value);

        return $this->memcached->set($key, $payload, $ttlSeconds ?? 0);
    }

    public function delete(string $key): bool
    {
        $this->assertKeyLength($key);
        $this->memcached->delete($key);
        $code = $this->memcached->getResultCode();

        return $code === Memcached::RES_SUCCESS || $code === Memcached::RES_NOTFOUND;
    }

    public function clear(): bool
    {
        return $this->memcached->flush();
    }

    public function fetchMultiple(iterable $keys): array
    {
        $keysArr = [];
        foreach ($keys as $k) {
            $k = (string) $k;
            $this->assertKeyLength($k);
            $keysArr[] = $k;
        }

        if ($keysArr === []) {
            return [];
        }

        $values = $this->memcached->getMulti($keysArr) ?: [];

        $out = [];
        foreach ($keysArr as $key) {
            if (!array_key_exists($key, $values)) {
                $out[$key] = ['hit' => false, 'value' => null];
                continue;
            }

            $out[$key] = ['hit' => true, 'value' => $this->unserializeValue($values[$key])];
        }

        return $out;
    }

    public function getMultiple(iterable $keys): array
    {
        $out = [];
        foreach ($this->fetchMultiple($keys) as $key => $f) {
            $out[$key] = $f['hit'] ? $f['value'] : null;
        }

        return $out;
    }

    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        $ttlSeconds = $this->normalizeTtlSeconds($ttl);

        $payloads = [];
        foreach ($values as $k => $v) {
            $k = (string) $k;
            $this->assertKeyLength($k);
            $payloads[$k] = $this->serializeValue($v);
        }

        if ($payloads === []) {
            return true;
        }

        // ext-memcached: setMulti принимает ttl вторым аргументом
        $ok = $this->memcached->setMulti($payloads, $ttlSeconds ?? 0);

        return (bool) $ok;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $keysArr = [];
        foreach ($keys as $k) {
            $k = (string) $k;
            $this->assertKeyLength($k);
            $keysArr[] = $k;
        }

        if ($keysArr === []) {
            return true;
        }

        // deleteMulti возвращает массив результатов, но на него нельзя полагаться как на bool
        $this->memcached->deleteMulti($keysArr);

        return true;
    }

    public function has(string $key): bool
    {
        $this->assertKeyLength($key);
        $this->memcached->get($key);

        return $this->memcached->getResultCode() !== Memcached::RES_NOTFOUND;
    }

    private function assertKeyLength(string $key): void
    {
        if (strlen($key) > self::MAX_KEY_LENGTH) {
            throw new CacheException('Cache key is too long for Memcached (max ' . self::MAX_KEY_LENGTH . ' chars).');
        }
    }

    private function serializeValue(mixed $value): string
    {
        return serialize($value);
    }

    private function unserializeValue(mixed $raw): mixed
    {
        if (!is_string($raw)) {
            return null;
        }

        return @unserialize($raw);
    }

    private function normalizeTtlSeconds(int|DateInterval|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if (is_int($ttl)) {
            return $ttl;
        }

        $now = new DateTimeImmutable();

        $dt = $now->add($ttl);

        return $dt->getTimestamp() - $now->getTimestamp();
    }
}
