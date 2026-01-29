<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Contracts;

use DateInterval;

/**
 * Низкоуровневый контракт драйвера кеша.
 *
 * Это внутренний интерфейс компонента, поверх которого строятся PSR-6 и PSR-16.
 */
interface DriverInterface
{
    /**
     * Проверяет, может ли драйвер работать в текущем окружении (наличие расширений, версий и т.д.).
     */
    public static function isSupported(): bool;

    /**
     * Возвращает значение и признак наличия ключа.
     *
     * Это нужно, чтобы корректно поддерживать значения `null` (PSR-16 допускает `null` как value).
     *
     * @return array{hit: bool, value: mixed}
     */
    public function fetch(string $key): array;

    /**
     * Удобный метод: возвращает value либо null, если ключа нет.
     *
     * Внимание: при использовании напрямую этот метод не позволяет отличить "нет ключа" от "value = null".
     */
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool;

    public function delete(string $key): bool;

    public function clear(): bool;

    /**
     * @param iterable<string> $keys
     * @return array<string, array{hit: bool, value: mixed}>
     */
    public function fetchMultiple(iterable $keys): array;

    /**
     * @param iterable<string> $keys
     * @return array<string, mixed>
     */
    public function getMultiple(iterable $keys): array;

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool;

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool;

    public function has(string $key): bool;
}
