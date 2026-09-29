<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Support;

use DateInterval;
use DateTimeImmutable;

use function is_int;
use function max;

/**
 * Нормализация TTL.
 *
 * Семантика PSR-16/PSR-6: `null` — без срока жизни (или TTL по умолчанию стора), `<= 0` — запись истекла сразу
 * и должна быть удалена.
 */
final class Ttl
{
    /**
     * Переводит TTL в секунды. Отрицательный `DateInterval` даёт 0.
     */
    public static function normalizeSeconds(int|DateInterval|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if (is_int($ttl)) {
            return $ttl;
        }

        $base = new DateTimeImmutable('@0');

        return max(0, $base->add($ttl)->getTimestamp());
    }

    /**
     * true, если запись с таким TTL не должна храниться (TTL 0 или отрицательный).
     */
    public static function isExpired(?int $seconds): bool
    {
        return $seconds !== null && $seconds <= 0;
    }
}
