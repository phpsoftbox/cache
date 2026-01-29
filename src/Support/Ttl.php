<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Support;

use DateInterval;
use DateTimeImmutable;

use function is_int;
use function max;

final class Ttl
{
    public static function normalizeSeconds(int|DateInterval|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if (is_int($ttl)) {
            return $ttl;
        }

        // DateInterval -> seconds
        $base = new DateTimeImmutable('@0');

        $end = $base->add($ttl);

        return max(0, $end->getTimestamp() - $base->getTimestamp());
    }
}
