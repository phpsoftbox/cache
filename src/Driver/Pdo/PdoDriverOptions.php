<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Driver\Pdo;

/**
 * Опции PDO-драйвера кеша.
 */
final readonly class PdoDriverOptions
{
    public function __construct(
        public PdoCacheSchema $schema = new PdoCacheSchema(),
        public PdoDriverEnum $driver = PdoDriverEnum::SQLITE,
        public bool $autoCreateTable = true,
    ) {
    }
}
