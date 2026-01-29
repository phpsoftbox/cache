<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Configurator;

use PDO;
use PhpSoftBox\Cache\Driver\Pdo\PdoConnectionOptions;

use function array_key_exists;

/**
 * Фабрика подключения PDO.
 *
 * Делает создание PDO DI-friendly и явным (dsn/login/password/options).
 */
final class PdoConnectionFactory
{
    public function create(PdoConnectionOptions $options): PDO
    {
        $pdo = new PDO($options->dsn, $options->username, $options->password, $options->pdoOptions);

        // дефолтно включаем исключения, если не переопределили
        if (!array_key_exists(PDO::ATTR_ERRMODE, $options->pdoOptions)) {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return $pdo;
    }
}
