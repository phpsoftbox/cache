<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Driver;

use DateInterval;
use InvalidArgumentException;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Contracts\ExpirationAwareDriverInterface;
use PhpSoftBox\Cache\Contracts\PrunableDriverInterface;
use PhpSoftBox\Cache\Support\CachePruneOptions;
use PhpSoftBox\Cache\Support\CachePruneResult;

use function is_array;
use function iterator_to_array;
use function time;

/**
 * Цепочка драйверов (L1 -> L2 -> L3 ...).
 *
 * - fetch(): ищет сверху вниз; при hit на нижнем уровне прогревает все уровни выше с оставшимся TTL записи
 *   (если нижний уровень реализует ExpirationAwareDriverInterface) либо с $warmupTtl (Memcached)
 * - set(): пишет во все уровни
 * - delete()/clear(): удаляет во всех уровнях
 */
final class ChainDriver implements DriverInterface, PrunableDriverInterface
{
    public const int DEFAULT_WARMUP_TTL = 60;

    /**
     * @param non-empty-list<DriverInterface> $drivers
     * @param int $warmupTtl TTL прогрева (сек), когда оставшийся срок записи на нижнем уровне неизвестен
     */
    public function __construct(
        private readonly array $drivers,
        private readonly int $warmupTtl = self::DEFAULT_WARMUP_TTL,
    ) {
        if ($drivers === []) {
            throw new InvalidArgumentException('ChainDriver requires at least one driver.');
        }

        if ($warmupTtl <= 0) {
            throw new InvalidArgumentException('ChainDriver warmup TTL must be greater than zero.');
        }
    }

    public static function isSupported(): bool
    {
        return true;
    }

    public function fetch(string $key): array
    {
        foreach ($this->drivers as $i => $driver) {
            if ($driver instanceof ExpirationAwareDriverInterface) {
                $f   = $driver->fetchWithExpiration($key);
                $ttl = $f['expiresAt'] === null ? null : $f['expiresAt'] - time();
            } else {
                $f   = $driver->fetch($key);
                $ttl = $this->warmupTtl;
            }

            if (!$f['hit']) {
                continue;
            }

            // прогреваем уровни выше (0..i-1) с оставшимся сроком жизни
            if ($ttl === null || $ttl > 0) {
                for ($j = 0; $j < $i; $j++) {
                    $this->drivers[$j]->set($key, $f['value'], $ttl);
                }
            }

            return ['hit' => true, 'value' => $f['value']];
        }

        return ['hit' => false, 'value' => null];
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

    public function clear(string $namespace = ''): bool
    {
        $ok = true;
        foreach ($this->drivers as $driver) {
            $ok = $driver->clear($namespace) && $ok;
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
        // iterable может быть генератором: материализуем, чтобы передать всем уровням
        $values = is_array($values) ? $values : iterator_to_array($values);

        $ok = true;
        foreach ($this->drivers as $driver) {
            $ok = $driver->setMultiple($values, $ttl) && $ok;
        }

        return $ok;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $keys = is_array($keys) ? $keys : iterator_to_array($keys, false);

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

    public function prune(?CachePruneOptions $options = null): CachePruneResult
    {
        $result = CachePruneResult::empty();

        foreach ($this->drivers as $driver) {
            if (!$driver instanceof PrunableDriverInterface) {
                $result = $result->merge(CachePruneResult::unsupported());
                continue;
            }

            $result = $result->merge($driver->prune($options));
        }

        return $result;
    }
}
