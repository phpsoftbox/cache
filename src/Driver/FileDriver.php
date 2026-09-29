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
use function bin2hex;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function is_int;
use function is_string;
use function mkdir;
use function random_bytes;
use function rename;
use function rtrim;
use function serialize;
use function sha1;
use function str_starts_with;
use function time;
use function unlink;
use function unserialize;

use const LOCK_EX;

/**
 * File-based драйвер.
 *
 * Хранит каждый ключ отдельным файлом `sha1(key).cache`. В файле — serialize() массива с ключом, моментом
 * истечения и значением; ключ нужен, чтобы clear() очищал только свой namespace.
 */
final class FileDriver implements DriverInterface, PrunableDriverInterface, ExpirationAwareDriverInterface
{
    public static function isSupported(): bool
    {
        return true;
    }

    public function __construct(
        private readonly string $directory,
    ) {
        if ($this->directory === '' || !is_dir($this->directory)) {
            @mkdir($this->directory, 0777, true);
        }
    }

    public function fetch(string $key): array
    {
        $f = $this->fetchWithExpiration($key);

        return ['hit' => $f['hit'], 'value' => $f['value']];
    }

    public function fetchWithExpiration(string $key): array
    {
        $miss = ['hit' => false, 'value' => null, 'expiresAt' => null];

        $path = $this->pathForKey($key);
        if (!file_exists($path)) {
            return $miss;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return $miss;
        }

        $payload = @unserialize($raw);
        if (!is_array($payload) || !array_key_exists('expiresAt', $payload) || !array_key_exists('value', $payload)) {
            @unlink($path);

            return $miss;
        }

        $expiresAt = $payload['expiresAt'];
        if ($expiresAt !== null && (!is_int($expiresAt) || $expiresAt <= time())) {
            @unlink($path);

            return $miss;
        }

        return ['hit' => true, 'value' => $payload['value'], 'expiresAt' => $expiresAt];
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

        $payload = serialize([
            'key'       => $key,
            'expiresAt' => $ttlSeconds === null ? null : time() + $ttlSeconds,
            'value'     => $value,
        ]);

        $path = $this->pathForKey($key);
        $tmp  = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
            @unlink($tmp);

            return false;
        }

        return @rename($tmp, $path);
    }

    public function delete(string $key): bool
    {
        $path = $this->pathForKey($key);
        if (!file_exists($path)) {
            return true;
        }

        return @unlink($path);
    }

    public function clear(string $namespace = ''): bool
    {
        if (!is_dir($this->directory)) {
            return true;
        }

        $prefix = $namespace . CacheKey::SEPARATOR;

        $ok = true;
        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
            if ($namespace !== '' && !$this->fileBelongsToNamespace($file, $prefix)) {
                continue;
            }

            $ok = @unlink($file) && $ok;
        }

        return $ok;
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

    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        $ok = true;
        foreach ($values as $key => $value) {
            $ok = $this->set((string) $key, $value, $ttl) && $ok;
        }

        return $ok;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $ok = true;
        foreach ($keys as $key) {
            $ok = $this->delete((string) $key) && $ok;
        }

        return $ok;
    }

    public function has(string $key): bool
    {
        return $this->fetch($key)['hit'];
    }

    public function prune(?CachePruneOptions $options = null): CachePruneResult
    {
        if (!is_dir($this->directory)) {
            return CachePruneResult::empty();
        }

        $options ??= CachePruneOptions::defaults();

        $now    = time();
        $result = CachePruneResult::empty();

        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }

            $result = $result->withScanned();

            $raw = @file_get_contents($file);
            if ($raw === false) {
                $result = $result->withFailed();
                continue;
            }

            $payload = @unserialize($raw, ['allowed_classes' => false]);
            if (!is_array($payload) || !array_key_exists('expiresAt', $payload) || !array_key_exists('value', $payload)) {
                $result = $this->removePrunedFile($file, $result, 'invalid');
                continue;
            }

            $expiresAt = $payload['expiresAt'];
            if ($expiresAt !== null && (!is_int($expiresAt) || $expiresAt <= $now)) {
                $result = $this->removePrunedFile($file, $result, 'expired');
                continue;
            }

            if ($expiresAt === null && $options->maxAgeSeconds !== null) {
                $modifiedAt = @filemtime($file);
                if ($modifiedAt === false) {
                    $result = $result->withFailed();
                    continue;
                }

                if ($modifiedAt <= $now - $options->maxAgeSeconds) {
                    $result = $this->removePrunedFile($file, $result, 'stale');
                }
            }
        }

        $temporaryCutoff = $now - $options->temporaryMaxAgeSeconds;
        foreach (glob($this->directory . '/*.tmp') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }

            $result     = $result->withScanned();
            $modifiedAt = @filemtime($file);
            if ($modifiedAt === false) {
                $result = $result->withFailed();
                continue;
            }

            if ($modifiedAt <= $temporaryCutoff) {
                $result = $this->removePrunedFile($file, $result, 'temporary');
            }
        }

        return $result;
    }

    private function pathForKey(string $key): string
    {
        // sha1 достаточно, т.к. это key->filename mapping, не криптография.
        return rtrim($this->directory, '/\\') . '/' . sha1($key) . '.cache';
    }

    /**
     * Файл без ключа (формат до 1.0 или битый) считается принадлежащим любому namespace: это кеш, лишнее удаление
     * безопаснее, чем пережившие clear() данные.
     */
    private function fileBelongsToNamespace(string $file, string $prefix): bool
    {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return false;
        }

        $payload = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($payload) || !isset($payload['key']) || !is_string($payload['key'])) {
            return true;
        }

        return str_starts_with($payload['key'], $prefix);
    }

    private function removePrunedFile(string $file, CachePruneResult $result, string $reason): CachePruneResult
    {
        if (!@unlink($file)) {
            return $result->withFailed();
        }

        return match ($reason) {
            'expired'   => $result->withExpired(),
            'stale'     => $result->withStale(),
            'invalid'   => $result->withInvalid(),
            'temporary' => $result->withTemporary(),
            default     => $result,
        };
    }
}
