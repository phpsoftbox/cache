<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests\Fixture;

/**
 * Значение с private/protected свойствами: serialize() даёт NUL-байты в именах свойств.
 */
final class PrivateStateValue
{
    protected string $label = 'protected';

    public function __construct(
        private readonly string $secret,
    ) {
    }

    public function secret(): string
    {
        return $this->secret;
    }

    public function label(): string
    {
        return $this->label;
    }
}
