<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Driver\Pdo;

/**
 * Схема таблицы кеша в БД.
 */
final readonly class PdoCacheSchema
{
    public function __construct(
        public string $table = 'psb_cache',
        public string $keyColumn = 'cache_key',
        public string $valueColumn = 'cache_value',
        public string $expirationDatetimeColumn = 'expiration_datetime',
        public string $createdDatetimeColumn = 'created_datetime',
    ) {
    }
}
