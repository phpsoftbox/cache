<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Driver;

use DateInterval;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Contracts\ExpirationAwareDriverInterface;
use PhpSoftBox\Cache\Contracts\PrunableDriverInterface;
use PhpSoftBox\Cache\Exception\CacheException;
use PhpSoftBox\Cache\Support\CacheKey;
use PhpSoftBox\Cache\Support\CachePruneOptions;
use PhpSoftBox\Cache\Support\CachePruneResult;
use PhpSoftBox\Cache\Support\Ttl;
use Redis;

use function addcslashes;
use function array_keys;
use function extension_loaded;
use function is_array;
use function is_int;
use function is_string;
use function serialize;
use function strlen;
use function time;
use function unserialize;

/**
 * Redis driver (ext-redis).
 *
 * clear() с namespace удаляет только ключи `<namespace>:*` (SCAN + UNLINK), не трогая остальные данные
 * базы. clear() без namespace — FLUSHDB текущей базы.
 */
final class RedisDriver implements DriverInterface, PrunableDriverInterface, ExpirationAwareDriverInterface
{
    private const int MAX_KEY_LENGTH = 250;

    private const int SCAN_COUNT = 1000;

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
        if (!is_string($value)) {
            return ['hit' => false, 'value' => null];
        }

        return ['hit' => true, 'value' => $this->unserializeValue($value)];
    }

    public function fetchWithExpiration(string $key): array
    {
        $this->assertKeyLength($key);

        // GET и TTL одним round-trip
        $results = $this->redis->multi(Redis::PIPELINE)
            ->get($key)
            ->ttl($key)
            ->exec();

        $value = is_array($results) ? ($results[0] ?? false) : false;
        if (!is_string($value)) {
            return ['hit' => false, 'value' => null, 'expiresAt' => null];
        }

        // TTL: -1 — без срока жизни, -2 — ключ исчез между командами
        $ttl = is_array($results) ? ($results[1] ?? -1) : -1;
        if ($ttl === -2) {
            return ['hit' => false, 'value' => null, 'expiresAt' => null];
        }

        return [
            'hit'       => true,
            'value'     => $this->unserializeValue($value),
            'expiresAt' => is_int($ttl) && $ttl >= 0 ? time() + $ttl : null,
        ];
    }

    public function get(string $key): mixed
    {
        $f = $this->fetch($key);

        return $f['hit'] ? $f['value'] : null;
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $this->assertKeyLength($key);
        $ttlSeconds = Ttl::normalizeSeconds($ttl);
        if (Ttl::isExpired($ttlSeconds)) {
            return $this->delete($key);
        }

        $payload = $this->serializeValue($value);

        if ($ttlSeconds === null) {
            return (bool) $this->redis->set($key, $payload);
        }

        return (bool) $this->redis->setex($key, $ttlSeconds, $payload);
    }

    public function delete(string $key): bool
    {
        $this->assertKeyLength($key);

        // отсутствие ключа — не ошибка
        return $this->redis->del($key) !== false;
    }

    public function clear(string $namespace = ''): bool
    {
        if ($namespace === '') {
            return (bool) $this->redis->flushDB();
        }

        // экранируем glob-символы, чтобы namespace сравнивался буквально
        $pattern  = addcslashes($namespace . CacheKey::SEPARATOR, '*?[]^\\') . '*';
        $iterator = null;
        $ok       = true;

        do {
            $keys = $this->redis->scan($iterator, $pattern, self::SCAN_COUNT);
            if (is_array($keys) && $keys !== []) {
                $ok = $this->redis->unlink($keys) !== false && $ok;
            }
        } while ($iterator !== 0 && $iterator !== null && $keys !== false);

        return $ok;
    }

    public function fetchMultiple(iterable $keys): array
    {
        $keysArr = $this->keyList($keys);
        if ($keysArr === []) {
            return [];
        }

        $values = $this->redis->mget($keysArr);

        $out = [];
        foreach ($keysArr as $i => $key) {
            $v         = is_array($values) ? ($values[$i] ?? false) : false;
            $out[$key] = is_string($v)
                ? ['hit' => true, 'value' => $this->unserializeValue($v)]
                : ['hit' => false, 'value' => null];
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
        $ttlSeconds = Ttl::normalizeSeconds($ttl);

        $payloads = [];
        foreach ($values as $k => $v) {
            $k = (string) $k;
            $this->assertKeyLength($k);
            $payloads[$k] = $v;
        }

        if ($payloads === []) {
            return true;
        }

        if (Ttl::isExpired($ttlSeconds)) {
            return $this->deleteMultiple(array_keys($payloads));
        }

        if ($ttlSeconds === null) {
            $serialized = [];
            foreach ($payloads as $k => $v) {
                $serialized[$k] = $this->serializeValue($v);
            }

            return (bool) $this->redis->mset($serialized);
        }

        // SETEX для каждого ключа одним round-trip: значение и TTL пишутся атомарно для ключа
        $pipeline = $this->redis->multi(Redis::PIPELINE);
        foreach ($payloads as $k => $v) {
            $pipeline->setex((string) $k, $ttlSeconds, $this->serializeValue($v));
        }

        $results = $pipeline->exec();
        if (!is_array($results)) {
            return false;
        }

        foreach ($results as $result) {
            if ($result !== true) {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $keysArr = $this->keyList($keys);
        if ($keysArr === []) {
            return true;
        }

        return $this->redis->del($keysArr) !== false;
    }

    public function has(string $key): bool
    {
        $this->assertKeyLength($key);

        return (int) $this->redis->exists($key) > 0;
    }

    public function prune(?CachePruneOptions $options = null): CachePruneResult
    {
        // Redis удаляет TTL-записи самостоятельно; generic prune не сканирует keyspace.
        return CachePruneResult::empty();
    }

    /**
     * @param iterable<string> $keys
     * @return list<string>
     */
    private function keyList(iterable $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $k = (string) $k;
            $this->assertKeyLength($k);
            $out[] = $k;
        }

        return $out;
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
}
