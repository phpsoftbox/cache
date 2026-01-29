<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Driver;

use DateInterval;
use DateTimeImmutable;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Exception\CacheException;
use Redis;

use function array_keys;
use function extension_loaded;
use function is_int;
use function max;
use function serialize;
use function strlen;
use function unserialize;

/**
 * Redis driver (ext-redis).
 */
final class RedisDriver implements DriverInterface
{
    private const int MAX_KEY_LENGTH = 250;

    public static function isSupported(): bool
    {
        return extension_loaded('redis');
    }

    public function __construct(
        private readonly Redis $redis,
    ) {
        if (!self::isSupported()) {
            throw new CacheException('Redis extension (ext-redis) is required.');
        }
    }

    public function fetch(string $key): array
    {
        $this->assertKeyLength($key);
        $value = $this->redis->get($key);
        if ($value === false) {
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
        $payload    = $this->serializeValue($value);
        $ttlSeconds = $this->normalizeTtlSeconds($ttl);

        if ($ttlSeconds === null) {
            return $this->redis->set($key, $payload);
        }

        return $this->redis->setex($key, max(1, $ttlSeconds), $payload);
    }

    public function delete(string $key): bool
    {
        $this->assertKeyLength($key);

        return (int) $this->redis->del($key) > 0;
    }

    public function clear(): bool
    {
        // очищаем только текущую базу
        return $this->redis->flushDB();
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

        $values = $this->redis->mget($keysArr);

        $out = [];
        foreach ($keysArr as $i => $key) {
            $v         = $values[$i] ?? false;
            $out[$key] = $v === false
                ? ['hit' => false, 'value' => null]
                : ['hit' => true, 'value' => $this->unserializeValue($v)];
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

        $ok = (bool) $this->redis->mset($payloads);

        if ($ttlSeconds !== null) {
            foreach (array_keys($payloads) as $key) {
                $ok = $this->redis->expire($key, max(1, $ttlSeconds)) && $ok;
            }
        }

        return $ok;
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

        return (int) $this->redis->del($keysArr) > 0;
    }

    public function has(string $key): bool
    {
        $this->assertKeyLength($key);

        return (bool) $this->redis->exists($key);
    }

    private function assertKeyLength(string $key): void
    {
        if (strlen($key) > self::MAX_KEY_LENGTH) {
            throw new CacheException('Cache key is too long for Redis (max ' . self::MAX_KEY_LENGTH . ' chars).');
        }
    }

    private function serializeValue(mixed $value): string
    {
        return serialize($value);
    }

    private function unserializeValue(string $raw): mixed
    {
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
