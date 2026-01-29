<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Psr16;

use Psr\SimpleCache\InvalidArgumentException;

final class InvalidKeyException extends \InvalidArgumentException implements InvalidArgumentException
{
}
