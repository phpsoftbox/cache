<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Configurator\CacheBuilder;
use PhpSoftBox\Cache\Contracts\CacheServiceInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

#[CoversNothing]
final class CacheServiceInterfaceTest extends TestCase
{
    /**
     * Проверяет, что Cache реализует и PSR-16 CacheInterface, и расширенный CacheServiceInterface.
     */
    #[Test]
    public function cacheImplementsPsr16AndExtendedInterface(): void
    {
        $cache = CacheBuilder::fromConfig([
            'default' => 'default',
            'stores'  => [
                'default' => [
                    'driver'    => 'array',
                    'namespace' => 'app',
                ],
            ],
        ]);

        self::assertInstanceOf(CacheInterface::class, $cache);
        self::assertInstanceOf(CacheServiceInterface::class, $cache);
    }
}
