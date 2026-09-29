<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Driver;

use DateInterval;
use DateTimeImmutable;
use PDO;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Contracts\ExpirationAwareDriverInterface;
use PhpSoftBox\Cache\Contracts\PrunableDriverInterface;
use PhpSoftBox\Cache\Driver\Pdo\PdoCacheSchema;
use PhpSoftBox\Cache\Driver\Pdo\PdoDriverEnum;
use PhpSoftBox\Cache\Driver\Pdo\PdoDriverOptions;
use PhpSoftBox\Cache\Exception\CacheException;
use PhpSoftBox\Cache\Support\CacheKey;
use PhpSoftBox\Cache\Support\CachePruneOptions;
use PhpSoftBox\Cache\Support\CachePruneResult;
use PhpSoftBox\Cache\Support\Ttl;

use function base64_decode;
use function base64_encode;
use function extension_loaded;
use function is_array;
use function is_numeric;
use function is_resource;
use function is_string;
use function serialize;
use function sprintf;
use function str_replace;
use function stream_get_contents;
use function strlen;
use function time;
use function unserialize;

use const DATE_ATOM;

/**
 * PDO driver.
 *
 * Хранит значения в таблице в БД. Значение — base64(serialize()) в текстовой колонке: так в TEXT безопасно
 * попадают бинарные строки и объекты с private/protected свойствами (serialize() даёт NUL-байты, которые
 * Postgres в TEXT не принимает).
 */
final class PdoDriver implements DriverInterface, PrunableDriverInterface, ExpirationAwareDriverInterface
{
    private const int MAX_KEY_LENGTH = 255;

    private PdoCacheSchema $schema;

    public static function isSupported(): bool
    {
        return extension_loaded('pdo');
    }

    public function __construct(
        private readonly PDO $pdo,
        private readonly PdoDriverOptions $options,
    ) {
        if (!self::isSupported()) {
            throw new CacheException('PDO extension (ext-pdo) is required.');
        }

        $this->schema = $options->schema;

        if ($this->options->autoCreateTable) {
            $this->createTableIfNotExists();
        }
    }

    public function fetch(string $key): array
    {
        $f = $this->fetchWithExpiration($key);

        return ['hit' => $f['hit'], 'value' => $f['value']];
    }

    public function fetchWithExpiration(string $key): array
    {
        $this->assertKeyLength($key);

        $miss = ['hit' => false, 'value' => null, 'expiresAt' => null];

        $sql = sprintf(
            'SELECT %s, %s FROM %s WHERE %s = :key LIMIT 1',
            $this->qi($this->schema->valueColumn),
            $this->qi($this->schema->expirationDatetimeColumn),
            $this->qi($this->schema->table),
            $this->qi($this->schema->keyColumn),
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return $miss;
        }

        $expirationDatetime = $row[$this->schema->expirationDatetimeColumn] ?? null;
        $expiresAt          = is_numeric($expirationDatetime) ? (int) $expirationDatetime : null;
        if ($expiresAt !== null && $expiresAt <= time()) {
            $this->delete($key);

            return $miss;
        }

        $decoded = $this->unserializeValue($row[$this->schema->valueColumn] ?? null);
        if (!$decoded['ok']) {
            // формат до 1.0 (serialize без base64) или битая запись — промах
            $this->delete($key);

            return $miss;
        }

        return ['hit' => true, 'value' => $decoded['value'], 'expiresAt' => $expiresAt];
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

        $expirationDatetime = $ttlSeconds === null ? null : time() + $ttlSeconds;

        $payload = $this->serializeValue($value);
        $now     = $this->nowString();

        $table      = $this->qi($this->schema->table);
        $keyCol     = $this->qi($this->schema->keyColumn);
        $valCol     = $this->qi($this->schema->valueColumn);
        $expCol     = $this->qi($this->schema->expirationDatetimeColumn);
        $createdCol = $this->qi($this->schema->createdDatetimeColumn);

        $params = [
            'key'                 => $key,
            'value'               => $payload,
            'expiration_datetime' => $expirationDatetime,
            'created_datetime'    => $now,
        ];

        $sql = match ($this->options->driver) {
            PdoDriverEnum::PGSQL => "
                INSERT INTO {$table} ({$keyCol}, {$valCol}, {$expCol}, {$createdCol})
                VALUES (:key, :value, :expiration_datetime, :created_datetime)
                ON CONFLICT ({$keyCol}) DO UPDATE
                SET
                    {$valCol} = EXCLUDED.{$this->qiRaw($this->schema->valueColumn)},
                    {$expCol} = EXCLUDED.{$this->qiRaw($this->schema->expirationDatetimeColumn)}
            ",
            PdoDriverEnum::MYSQL => "
                INSERT INTO {$table} ({$keyCol}, {$valCol}, {$expCol}, {$createdCol})
                VALUES (:key, :value, :expiration_datetime, :created_datetime)
                ON DUPLICATE KEY UPDATE {$valCol} = VALUES({$valCol}), {$expCol} = VALUES({$expCol})
            ",
            PdoDriverEnum::SQLITE => "
                INSERT INTO {$table} ({$keyCol}, {$valCol}, {$expCol}, {$createdCol})
                VALUES (:key, :value, :expiration_datetime, :created_datetime)
                ON CONFLICT({$keyCol}) DO UPDATE
                SET
                    {$valCol} = excluded.{$this->qiRaw($this->schema->valueColumn)},
                    {$expCol} = excluded.{$this->qiRaw($this->schema->expirationDatetimeColumn)}
            ",
        };

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    public function delete(string $key): bool
    {
        $this->assertKeyLength($key);

        $sql = sprintf(
            'DELETE FROM %s WHERE %s = :key',
            $this->qi($this->schema->table),
            $this->qi($this->schema->keyColumn),
        );

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute(['key' => $key]);
    }

    public function clear(string $namespace = ''): bool
    {
        if ($namespace === '') {
            $stmt = $this->pdo->prepare(sprintf('DELETE FROM %s', $this->qi($this->schema->table)));

            return $stmt->execute();
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s LIKE :prefix ESCAPE \'!\'',
            $this->qi($this->schema->table),
            $this->qi($this->schema->keyColumn),
        );

        $prefix = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $namespace . CacheKey::SEPARATOR);

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute(['prefix' => $prefix . '%']);
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
        $this->assertKeyLength($key);

        return $this->fetch($key)['hit'];
    }

    public function prune(?CachePruneOptions $options = null): CachePruneResult
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE %s IS NOT NULL AND %s <= :now',
            $this->qi($this->schema->table),
            $this->qi($this->schema->expirationDatetimeColumn),
            $this->qi($this->schema->expirationDatetimeColumn),
        );

        $stmt = $this->pdo->prepare($sql);
        if (!$stmt->execute(['now' => time()])) {
            return new CachePruneResult(failed: 1);
        }

        return new CachePruneResult(expired: (int) $stmt->rowCount());
    }

    private function createTableIfNotExists(): void
    {
        // максимально "универсально". Для sqlite/mysql/pg это сработает.
        // key: PRIMARY KEY
        // value: TEXT (base64 от serialize)
        // expiration_datetime: BIGINT NULL
        // created_datetime: VARCHAR(64)

        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (%s VARCHAR(255) PRIMARY KEY, %s TEXT NOT NULL, %s BIGINT NULL, %s VARCHAR(64) NOT NULL)',
            $this->qi($this->schema->table),
            $this->qi($this->schema->keyColumn),
            $this->qi($this->schema->valueColumn),
            $this->qi($this->schema->expirationDatetimeColumn),
            $this->qi($this->schema->createdDatetimeColumn),
        );

        $this->pdo->exec($sql);
    }

    private function serializeValue(mixed $value): string
    {
        return base64_encode(serialize($value));
    }

    /**
     * @return array{ok: bool, value: mixed}
     */
    private function unserializeValue(mixed $raw): array
    {
        if (is_resource($raw)) {
            $raw = stream_get_contents($raw);
        }

        if (!is_string($raw)) {
            return ['ok' => false, 'value' => null];
        }

        $serialized = base64_decode($raw, true);
        if ($serialized === false) {
            return ['ok' => false, 'value' => null];
        }

        $value = @unserialize($serialized);
        if ($value === false && $serialized !== serialize(false)) {
            return ['ok' => false, 'value' => null];
        }

        return ['ok' => true, 'value' => $value];
    }

    private function nowString(): string
    {
        return new DateTimeImmutable('now')->format(DATE_ATOM);
    }

    private function qi(string $identifier): string
    {
        $quote = match ($this->options->driver) {
            PdoDriverEnum::MYSQL => '`',
            default              => '"',
        };

        return $quote . str_replace($quote, $quote . $quote, $identifier) . $quote;
    }

    /**
     * Для выражений типа EXCLUDED.<col> / excluded.<col> нам нужно quoting, но без повторного обрамления.
     */
    private function qiRaw(string $identifier): string
    {
        return match ($this->options->driver) {
            PdoDriverEnum::MYSQL => '`' . str_replace('`', '``', $identifier) . '`',
            default              => '"' . str_replace('"', '""', $identifier) . '"',
        };
    }

    private function assertKeyLength(string $key): void
    {
        if (strlen($key) > self::MAX_KEY_LENGTH) {
            throw new CacheException('Cache key is too long for PDO store (max ' . self::MAX_KEY_LENGTH . ' chars).');
        }
    }
}
