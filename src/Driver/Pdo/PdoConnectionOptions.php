<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Driver\Pdo;

/**
 * Настройки подключения PDO.
 */
final readonly class PdoConnectionOptions
{
    /**
     * @param array<int, mixed> $pdoOptions
     */
    public function __construct(
        public string $dsn,
        public ?string $username = null,
        public ?string $password = null,
        public array $pdoOptions = [],
    ) {
    }
}
