<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Configurator\BuiltInDriverFactory;
use PhpSoftBox\Cache\Configurator\CacheConfig;
use PhpSoftBox\Cache\Configurator\ChainDriverFactory;
use PhpSoftBox\Cache\Driver\ChainDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainDriverFactory::class)]
final class ChainDriverFactoryTest extends TestCase
{
    /**
     * Проверяет, что ChainDriverFactory создаёт ChainDriver из options[stores].
     */
    #[Test]
    public function createsChainDriver(): void
    {
        $builtIn = new BuiltInDriverFactory();

        $factory = new ChainDriverFactory([$builtIn]);

        $driver = $factory->create(new CacheConfig(
            driver: 'chain',
            options: [
                'stores' => ['array', 'array'],
            ],
        ));

        self::assertInstanceOf(ChainDriver::class, $driver);
        self::assertTrue($driver->set('a', 1));
        self::assertSame(1, $driver->get('a'));
    }
}
