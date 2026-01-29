<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Driver;

use DateInterval;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Support\Ttl;

use function array_key_exists;
use function bin2hex;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_array;
use function is_dir;
use function is_int;
use function max;
use function mkdir;
use function random_bytes;
use function rename;
use function rtrim;
use function serialize;
use function sha1;
use function time;
use function unlink;
use function unserialize;

use const LOCK_EX;

/**
 * File-based драйвер.
 *
 * Хранит каждый ключ отдельным файлом. Значения сериализуются через PHP serialize.
 */
final class FileDriver implements DriverInterface
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
        $path = $this->pathForKey($key);
        if (!file_exists($path)) {
            return ['hit' => false, 'value' => null];
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return ['hit' => false, 'value' => null];
        }

        $payload = @unserialize($raw);
        if (!is_array($payload) || !array_key_exists('expiresAt', $payload) || !array_key_exists('value', $payload)) {
            @unlink($path);

            return ['hit' => false, 'value' => null];
        }

        $expiresAt = $payload['expiresAt'];
        if ($expiresAt !== null) {
            if (!is_int($expiresAt) || $expiresAt < time()) {
                @unlink($path);

                return ['hit' => false, 'value' => null];
            }
        }

        return ['hit' => true, 'value' => $payload['value']];
    }

    public function get(string $key): mixed
    {
        $f = $this->fetch($key);

        return $f['hit'] ? $f['value'] : null;
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $ttlSeconds = Ttl::normalizeSeconds($ttl);

        $expiresAt = null;
        if ($ttlSeconds !== null) {
            $expiresAt = time() + max(0, $ttlSeconds);
        }

        $payload = serialize([
            'expiresAt' => $expiresAt,
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

    public function clear(): bool
    {
        if (!is_dir($this->directory)) {
            return true;
        }

        $ok = true;
        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
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

    private function pathForKey(string $key): string
    {
        // sha1 достаточно, т.к. это key->filename mapping, не криптография.
        return rtrim($this->directory, '/\\') . '/' . sha1($key) . '.cache';
    }
}
