<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Psr6;

use Psr\Cache\CacheException;
use RuntimeException;

final class RuntimeCacheException extends RuntimeException implements CacheException
{
}
