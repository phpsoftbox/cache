<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Contracts;

use PhpSoftBox\Cache\Support\CachePruneOptions;
use PhpSoftBox\Cache\Support\CachePruneResult;

/**
 * Optional capability for cache drivers that can remove obsolete entries without clear().
 */
interface PrunableDriverInterface
{
    public function prune(?CachePruneOptions $options = null): CachePruneResult;
}
