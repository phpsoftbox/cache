<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Support;

use InvalidArgumentException;

final readonly class CachePruneOptions
{
    public const int DEFAULT_TEMPORARY_MAX_AGE_SECONDS = 86400;

    public function __construct(
        public ?int $maxAgeSeconds = null,
        public int $temporaryMaxAgeSeconds = self::DEFAULT_TEMPORARY_MAX_AGE_SECONDS,
    ) {
        if ($this->maxAgeSeconds !== null && $this->maxAgeSeconds < 0) {
            throw new InvalidArgumentException('Cache prune max age must be greater than or equal to zero.');
        }

        if ($this->temporaryMaxAgeSeconds < 0) {
            throw new InvalidArgumentException('Cache prune temporary max age must be greater than or equal to zero.');
        }
    }

    public static function defaults(): self
    {
        return new self();
    }
}
