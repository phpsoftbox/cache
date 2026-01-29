<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Driver;

use DateInterval;
use PhpSoftBox\Cache\Contracts\DriverInterface;

/**
 * Цепочка драйверов (L1 -> L2 -> L3 ...).
 *
 * - fetch(): ищет сверху вниз; при hit на нижнем уровне прогревает все уровни выше
 * - set(): пишет во все уровни
 * - delete(): удаляет во всех уровнях
 */
final class ChainDriver implements DriverInterface
{
    /**
     * @param non-empty-list<DriverInterface> $drivers
     */
    public function __construct(
        private readonly array $drivers,
    ) {
    }

    public static function isSupported(): bool
    {
        return true;
    }

    public function fetch(string $key): array
    {
        $miss = ['hit' => false, 'value' => null];

        foreach ($this->drivers as $i => $driver) {
            $f = $driver->fetch($key);
            if (!$f['hit']) {
                continue;
            }

            // про��реваем уровни выше (0..i-1)
            for ($j = 0; $j < $i; $j++) {
                $this->drivers[$j]->set($key, $f['value']);
            }

            return $f;
        }

        return $miss;
    }

    public function get(string $key): mixed
    {
        $f = $this->fetch($key);

        return $f['hit'] ? $f['value'] : null;
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $ok = true;
        foreach ($this->drivers as $driver) {
            $ok = $driver->set($key, $value, $ttl) && $ok;
        }

        return $ok;
    }

    public function delete(string $key): bool
    {
        $ok = true;
        foreach ($this->drivers as $driver) {
            $ok = $driver->delete($key) && $ok;
        }

        return $ok;
    }

    public function clear(): bool
    {
        $ok = true;
        foreach ($this->drivers as $driver) {
            $ok = $driver->clear() && $ok;
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
        foreach ($this->fetchMultiple($keys) as $k => $f) {
            $result[$k] = $f['hit'] ? $f['value'] : null;
        }

        return $result;
    }

    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        $ok = true;
        foreach ($this->drivers as $driver) {
            $ok = $driver->setMultiple($values, $ttl) && $ok;
        }

        return $ok;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $ok = true;
        foreach ($this->drivers as $driver) {
            $ok = $driver->deleteMultiple($keys) && $ok;
        }

        return $ok;
    }

    public function has(string $key): bool
    {
        foreach ($this->drivers as $driver) {
            if ($driver->has($key)) {
                return true;
            }
        }

        return false;
    }
}
