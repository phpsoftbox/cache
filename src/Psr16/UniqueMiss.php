<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Psr16;

/**
 * Уникальный маркер miss, чтобы отличать "ключ отсутствует" от "значение равно null".
 */
final class UniqueMiss
{
    private static ?object $value = null;

    public static function value(): object
    {
        return self::$value ??= new class () {
        };
    }
}
