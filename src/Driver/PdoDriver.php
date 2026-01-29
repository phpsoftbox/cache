<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Driver;

use DateInterval;
use DateTimeImmutable;
use PDO;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Driver\Pdo\PdoCacheSchema;
use PhpSoftBox\Cache\Driver\Pdo\PdoDriverEnum;
use PhpSoftBox\Cache\Driver\Pdo\PdoDriverOptions;
use PhpSoftBox\Cache\Exception\CacheException;

use function extension_loaded;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function max;
use function serialize;
use function sprintf;
use function str_replace;
use function strlen;
use function time;
use function unserialize;

use const DATE_ATOM;

/**
 * PDO driver.
 *
 * Хранит значения в таблице в БД.
 */
final class PdoDriver implements DriverInterface
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
        $this->assertKeyLength($key);

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
            return ['hit' => false, 'value' => null];
        }

        $expirationDatetime = $row[$this->schema->expirationDatetimeColumn] ?? null;
        if (is_numeric($expirationDatetime) && (int) $expirationDatetime < time()) {
            $this->delete($key);

            return ['hit' => false, 'value' => null];
        }

        $raw = $row[$this->schema->valueColumn] ?? null;

        return ['hit' => true, 'value' => $this->unserializeValue($raw)];
    }

    public function get(string $key): mixed
    {
        $f = $this->fetch($key);

        return $f['hit'] ? $f['value'] : null;
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $this->assertKeyLength($key);

        $ttlSeconds         = $this->normalizeTtlSeconds($ttl);
        $expirationDatetime = $ttlSeconds === null ? null : time() + max(0, $ttlSeconds);

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
                ON CONFLICT ({$keyCol}) DO UPDATE SET
                    {$valCol} = EXCLUDED.{$this->qiRaw($this->schema->valueColumn)},
                    {$expCol} = EXCLUDED.{$this->qiRaw($this->schema->expirationDatetimeColumn)}
            ",
            PdoDriverEnum::MYSQL => "
                INSERT INTO {$table} ({$keyCol}, {$valCol}, {$expCol}, {$createdCol})
                VALUES (:key, :value, :expiration_datetime, :created_datetime)
                ON DUPLICATE KEY UPDATE
                    {$valCol} = VALUES({$valCol}),
                    {$expCol} = VALUES({$expCol})
            ",
            PdoDriverEnum::SQLITE => "
                INSERT INTO {$table} ({$keyCol}, {$valCol}, {$expCol}, {$createdCol})
                VALUES (:key, :value, :expiration_datetime, :created_datetime)
                ON CONFLICT({$keyCol}) DO UPDATE SET
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

    public function clear(): bool
    {
        $sql  = sprintf('DELETE FROM %s', $this->qi($this->schema->table));
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute();
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

    private function createTableIfNotExists(): void
    {
        // максимально "универсально". Для sqlite/mysql/pg это сработает.
        // key: PRIMARY KEY
        // value: TEXT
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
        return serialize($value);
    }

    private function unserializeValue(mixed $raw): mixed
    {
        if (!is_string($raw)) {
            return null;
        }

        $value = @unserialize($raw);

        return $value;
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
