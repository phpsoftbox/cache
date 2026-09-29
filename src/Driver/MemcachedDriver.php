<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Driver;

use DateInterval;
use Memcached;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Contracts\PrunableDriverInterface;
use PhpSoftBox\Cache\Exception\CacheException;
use PhpSoftBox\Cache\Support\CacheKey;
use PhpSoftBox\Cache\Support\CachePruneOptions;
use PhpSoftBox\Cache\Support\CachePruneResult;
use PhpSoftBox\Cache\Support\Ttl;

use function array_key_exists;
use function array_keys;
use function array_pop;
use function array_values;
use function bin2hex;
use function count;
use function explode;
use function extension_loaded;
use function implode;
use function is_array;
use function is_string;
use function random_bytes;
use function serialize;
use function strlen;
use function time;
use function unserialize;

/**
 * Memcached driver (ext-memcached).
 *
 * Memcached не умеет удалять ключи по префиксу, поэтому namespace версионируется: у каждого уровня namespace
 * (`app`, `app:login`) есть случайная версия, которая хранится в служебном ключе `@ns:<namespace>` и входит в
 * реальный ключ (`app@<v1>:login@<v2>:key`). clear() с namespace меняет версию — старые записи становятся
 * недоступны и вытесняются по TTL/LRU. clear() без namespace — flush() всего сервера.
 *
 * Цена версионирования: операция с ключом в namespace делает дополнительный запрос версий (один getMulti).
 */
final class MemcachedDriver implements DriverInterface, PrunableDriverInterface
{
    private const int MAX_KEY_LENGTH = 250;

    /**
     * TTL больше 30 дней Memcached трактует как абсолютный unix timestamp.
     */
    private const int MAX_RELATIVE_TTL = 2592000;

    private const string VERSION_KEY_PREFIX = '@ns:';

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
        $realKey = $this->realKeys([$key])[$key];

        $value = $this->memcached->get($realKey);
        if ($this->memcached->getResultCode() !== Memcached::RES_SUCCESS) {
            // RES_NOTFOUND, ошибка соединения и т.п. — промах
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
        $ttlSeconds = Ttl::normalizeSeconds($ttl);
        if (Ttl::isExpired($ttlSeconds)) {
            return $this->delete($key);
        }

        $realKey = $this->realKeys([$key])[$key];

        return $this->memcached->set($realKey, $this->serializeValue($value), $this->expiration($ttlSeconds));
    }

    public function delete(string $key): bool
    {
        $realKey = $this->realKeys([$key])[$key];

        $this->memcached->delete($realKey);
        $code = $this->memcached->getResultCode();

        return $code === Memcached::RES_SUCCESS || $code === Memcached::RES_NOTFOUND;
    }

    public function clear(string $namespace = ''): bool
    {
        if ($namespace === '') {
            return $this->memcached->flush();
        }

        return $this->memcached->set($this->versionKey($namespace), $this->newVersion(), 0);
    }

    public function fetchMultiple(iterable $keys): array
    {
        $realKeys = $this->realKeys($this->keyList($keys));
        if ($realKeys === []) {
            return [];
        }

        $values = $this->memcached->getMulti(array_values($realKeys));
        if (!is_array($values)) {
            $values = [];
        }

        $out = [];
        foreach ($realKeys as $key => $realKey) {
            $out[$key] = array_key_exists($realKey, $values)
                ? ['hit' => true, 'value' => $this->unserializeValue($values[$realKey])]
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

        $items = [];
        foreach ($values as $k => $v) {
            $items[(string) $k] = $v;
        }

        if ($items === []) {
            return true;
        }

        if (Ttl::isExpired($ttlSeconds)) {
            return $this->deleteMultiple(array_keys($items));
        }

        $realKeys = $this->realKeys(array_keys($items));

        $payloads = [];
        foreach ($items as $k => $v) {
            $payloads[$realKeys[$k]] = $this->serializeValue($v);
        }

        return $this->memcached->setMulti($payloads, $this->expiration($ttlSeconds));
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $realKeys = $this->realKeys($this->keyList($keys));
        if ($realKeys === []) {
            return true;
        }

        $results = $this->memcached->deleteMulti(array_values($realKeys));
        if (!is_array($results)) {
            return false;
        }

        foreach ($results as $result) {
            // true — удалён, RES_NOTFOUND — ключа не было
            if ($result !== true && $result !== Memcached::RES_NOTFOUND) {
                return false;
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
        // Memcached удаляет TTL-записи самостоятельно; generic prune не сканирует keyspace.
        return CachePruneResult::empty();
    }

    /**
     * Секунды TTL -> expiration для Memcached: до 30 дней — относительное значение, больше — абсолютный timestamp.
     */
    private function expiration(?int $ttlSeconds): int
    {
        if ($ttlSeconds === null) {
            return 0;
        }

        return $ttlSeconds > self::MAX_RELATIVE_TTL ? time() + $ttlSeconds : $ttlSeconds;
    }

    /**
     * Реальные ключи с версиями namespace. Версии всех уровней читаются одним getMulti.
     *
     * @param list<string> $keys
     * @return array<string, string>
     */
    private function realKeys(array $keys): array
    {
        $parsed      = [];
        $versionKeys = [];
        foreach ($keys as $key) {
            $segments = explode(CacheKey::SEPARATOR, $key);
            $name     = array_pop($segments);

            $namespaces = [];
            $path       = [];
            foreach ($segments as $segment) {
                $path[]       = $segment;
                $namespace    = implode(CacheKey::SEPARATOR, $path);
                $namespaces[] = $namespace;

                $versionKeys[$namespace] = $this->versionKey($namespace);
            }

            $parsed[$key] = ['segments' => $segments, 'namespaces' => $namespaces, 'name' => $name];
        }

        $versions = $versionKeys === [] ? [] : $this->versions($versionKeys);

        $out = [];
        foreach ($parsed as $key => $item) {
            $parts = [];
            foreach ($item['segments'] as $i => $segment) {
                $parts[] = $segment . '@' . $versions[$item['namespaces'][$i]];
            }

            $parts[] = $item['name'];
            $realKey = count($parts) === 1 ? (string) $key : implode(CacheKey::SEPARATOR, $parts);

            $this->assertKeyLength($realKey);
            $out[(string) $key] = $realKey;
        }

        return $out;
    }

    /**
     * @param array<string, string> $versionKeys namespace => служебный ключ версии
     * @return array<string, string> namespace => версия
     */
    private function versions(array $versionKeys): array
    {
        $stored = $this->memcached->getMulti(array_values($versionKeys));
        if (!is_array($stored)) {
            $stored = [];
        }

        $versions = [];
        foreach ($versionKeys as $namespace => $versionKey) {
            $version = $stored[$versionKey] ?? null;
            if (!is_string($version)) {
                // версии нет (первое обращение или вытеснена): add() не перетирает версию,
                // которую успел записать параллельный процесс
                $version = $this->newVersion();
                if (!$this->memcached->add($versionKey, $version, 0)) {
                    $existing = $this->memcached->get($versionKey);
                    if ($this->memcached->getResultCode() === Memcached::RES_SUCCESS && is_string($existing)) {
                        $version = $existing;
                    }
                }
            }

            $versions[$namespace] = $version;
        }

        return $versions;
    }

    private function versionKey(string $namespace): string
    {
        $versionKey = self::VERSION_KEY_PREFIX . $namespace;
        $this->assertKeyLength($versionKey);

        return $versionKey;
    }

    private function newVersion(): string
    {
        return bin2hex(random_bytes(4));
    }

    /**
     * @param iterable<string> $keys
     * @return list<string>
     */
    private function keyList(iterable $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[] = (string) $k;
        }

        return $out;
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
}
