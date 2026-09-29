<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Support;

use InvalidArgumentException;

use function explode;
use function preg_match;

/**
 * Правила ключей и namespace.
 *
 * Ключ PSR-16/PSR-6 не может быть пустым и содержать зарезервированные символы `{}()/\@:`. Namespace — это
 * один или несколько сегментов, разделённых `:`; сегменты подчиняются тем же правилам, что и ключи. Поэтому
 * реальный ключ драйвера `namespace:key` однозначно разбирается на namespace и ключ, а ключи разных
 * namespace не смешиваются.
 */
final class CacheKey
{
    public const string SEPARATOR = ':';

    private const string RESERVED_PATTERN = '/[{}()\/\\\\@:]/';

    public static function isValid(string $key): bool
    {
        return $key !== '' && preg_match(self::RESERVED_PATTERN, $key) !== 1;
    }

    /**
     * Проверяет namespace: пустая строка (нет namespace) или сегменты через `:`.
     *
     * @throws InvalidArgumentException
     */
    public static function assertValidNamespace(string $namespace): void
    {
        if ($namespace === '') {
            return;
        }

        foreach (explode(self::SEPARATOR, $namespace) as $segment) {
            if (!self::isValid($segment)) {
                throw new InvalidArgumentException(
                    'Cache namespace "' . $namespace . '" is invalid: segments must be non-empty and must not contain {}()/\@.',
                );
            }
        }
    }

    /**
     * Добавляет к namespace вложенный namespace.
     */
    public static function nestNamespace(string $namespace, string $child): string
    {
        self::assertValidNamespace($child);

        if ($child === '') {
            return $namespace;
        }

        return $namespace === '' ? $child : $namespace . self::SEPARATOR . $child;
    }

    /**
     * Реальный ключ драйвера.
     */
    public static function join(string $namespace, string $key): string
    {
        return $namespace === '' ? $key : $namespace . self::SEPARATOR . $key;
    }
}
