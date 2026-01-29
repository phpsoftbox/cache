<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Driver\Pdo;

use InvalidArgumentException;
use PDO;

/**
 * Тип PDO-движка.
 *
 * Нужен, чтобы корректно генерировать SQL (upsert, quoting).
 */
enum PdoDriverEnum: string
{
    case SQLITE = 'sqlite';
    case MYSQL  = 'mysql';
    case PGSQL  = 'pgsql';

    public static function fromPdo(PDO $pdo): self
    {
        $name = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return match ($name) {
            'sqlite' => self::SQLITE,
            'mysql'  => self::MYSQL,
            'pgsql'  => self::PGSQL,
            default  => throw new InvalidArgumentException('Unsupported PDO driver: ' . $name),
        };
    }
}
