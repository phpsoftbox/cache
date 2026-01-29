<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Support;

final readonly class CachePruneResult
{
    public function __construct(
        public int $scanned = 0,
        public int $expired = 0,
        public int $stale = 0,
        public int $invalid = 0,
        public int $temporary = 0,
        public int $failed = 0,
        public int $unsupported = 0,
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    public static function unsupported(): self
    {
        return new self(unsupported: 1);
    }

    public function removed(): int
    {
        return $this->expired + $this->stale + $this->invalid + $this->temporary;
    }

    public function merge(self $result): self
    {
        return new self(
            scanned: $this->scanned + $result->scanned,
            expired: $this->expired + $result->expired,
            stale: $this->stale + $result->stale,
            invalid: $this->invalid + $result->invalid,
            temporary: $this->temporary + $result->temporary,
            failed: $this->failed + $result->failed,
            unsupported: $this->unsupported + $result->unsupported,
        );
    }

    public function withScanned(int $count = 1): self
    {
        return new self(
            scanned: $this->scanned + $count,
            expired: $this->expired,
            stale: $this->stale,
            invalid: $this->invalid,
            temporary: $this->temporary,
            failed: $this->failed,
            unsupported: $this->unsupported,
        );
    }

    public function withExpired(int $count = 1): self
    {
        return new self(
            scanned: $this->scanned,
            expired: $this->expired + $count,
            stale: $this->stale,
            invalid: $this->invalid,
            temporary: $this->temporary,
            failed: $this->failed,
            unsupported: $this->unsupported,
        );
    }

    public function withStale(int $count = 1): self
    {
        return new self(
            scanned: $this->scanned,
            expired: $this->expired,
            stale: $this->stale + $count,
            invalid: $this->invalid,
            temporary: $this->temporary,
            failed: $this->failed,
            unsupported: $this->unsupported,
        );
    }

    public function withInvalid(int $count = 1): self
    {
        return new self(
            scanned: $this->scanned,
            expired: $this->expired,
            stale: $this->stale,
            invalid: $this->invalid + $count,
            temporary: $this->temporary,
            failed: $this->failed,
            unsupported: $this->unsupported,
        );
    }

    public function withTemporary(int $count = 1): self
    {
        return new self(
            scanned: $this->scanned,
            expired: $this->expired,
            stale: $this->stale,
            invalid: $this->invalid,
            temporary: $this->temporary + $count,
            failed: $this->failed,
            unsupported: $this->unsupported,
        );
    }

    public function withFailed(int $count = 1): self
    {
        return new self(
            scanned: $this->scanned,
            expired: $this->expired,
            stale: $this->stale,
            invalid: $this->invalid,
            temporary: $this->temporary,
            failed: $this->failed + $count,
            unsupported: $this->unsupported,
        );
    }
}
