<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Psr6;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

use function is_int;
use function max;

final class CacheItem implements CacheItemInterface
{
    private bool $isHit;

    /**
     */
    public function __construct(
        private readonly string $key,
        mixed $value,
        bool $isHit,
    ) {
        $this->value = $value;
        $this->isHit = $isHit;
    }

    private mixed $value;

    private ?int $expiresAt = null;

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->isHit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->isHit = true;

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->expiresAt = $expiration?->getTimestamp();

        return $this;
    }

    public function expiresAfter(int|DateInterval|null $time): static
    {
        if ($time === null) {
            $this->expiresAt = null;

            return $this;
        }

        if (is_int($time)) {
            $this->expiresAt = new DateTimeImmutable()->getTimestamp() + max(0, $time);

            return $this;
        }

        $this->expiresAt = new DateTimeImmutable()->add($time)->getTimestamp();

        return $this;
    }

    public function getExpiresAt(): ?int
    {
        return $this->expiresAt;
    }
}
