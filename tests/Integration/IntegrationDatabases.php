<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests\Integration;

use PDO;
use PhpSoftBox\Cache\Configurator\PdoConnectionFactory;
use PhpSoftBox\Cache\Driver\Pdo\PdoConnectionOptions;
use PhpSoftBox\Cache\Driver\Pdo\PdoDriverEnum;

use function getenv;
use function sprintf;

/**
 * Хелперы для интеграционных тестов Cache (реальные БД).
 */
final class IntegrationDatabases
{
    public static function mariadbPdo(): array
    {
        $host = (string) (getenv('MYSQL_HOST') ?: 'mariadb');
        $db   = (string) (getenv('MYSQL_DATABASE') ?: 'phpsoftbox');
        $user = (string) (getenv('MYSQL_USER') ?: 'phpsoftbox');
        $pass = (string) (getenv('MYSQL_PASSWORD') ?: 'phpsoftbox');

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $db);

        $pdo = new PdoConnectionFactory()->create(new PdoConnectionOptions(
            dsn: $dsn,
            username: $user,
            password: $pass,
            pdoOptions: [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ],
        ));

        return ['pdo' => $pdo, 'driver' => PdoDriverEnum::MYSQL];
    }

    public static function postgresPdo(): array
    {
        $host = (string) (getenv('POSTGRES_HOST') ?: 'postgres');
        $db   = (string) (getenv('POSTGRES_DB') ?: 'phpsoftbox');
        $user = (string) (getenv('POSTGRES_USER') ?: 'phpsoftbox');
        $pass = (string) (getenv('POSTGRES_PASSWORD') ?: 'phpsoftbox');

        $dsn = sprintf('pgsql:host=%s;dbname=%s', $host, $db);

        $pdo = new PdoConnectionFactory()->create(new PdoConnectionOptions(
            dsn: $dsn,
            username: $user,
            password: $pass,
            pdoOptions: [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ],
        ));

        return ['pdo' => $pdo, 'driver' => PdoDriverEnum::PGSQL];
    }
}
