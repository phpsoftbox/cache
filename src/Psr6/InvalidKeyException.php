<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Psr6;

use Psr\Cache\InvalidArgumentException;

final class InvalidKeyException extends \InvalidArgumentException implements InvalidArgumentException
{
}
