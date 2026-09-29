<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Contracts;

/**
 * Драйвер, который умеет отдать вместе со значением момент истечения записи.
 *
 * Используется ChainDriver, чтобы прогревать верхние уровни с оставшимся TTL.
 */
interface ExpirationAwareDriverInterface
{
    /**
     * @return array{hit: bool, value: mixed, expiresAt: int|null} expiresAt — unix timestamp, null — без срока жизни
     */
    public function fetchWithExpiration(string $key): array;
}
