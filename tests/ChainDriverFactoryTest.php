<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use InvalidArgumentException;
use PhpSoftBox\Cache\Configurator\BuiltInDriverFactory;
use PhpSoftBox\Cache\Configurator\CacheConfig;
use PhpSoftBox\Cache\Configurator\ChainDriverFactory;
use PhpSoftBox\Cache\Driver\ChainDriver;
use PhpSoftBox\Cache\Driver\FileDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function glob;
use function random_bytes;
use function sys_get_temp_dir;

#[CoversClass(ChainDriverFactory::class)]
#[CoversMethod(ChainDriverFactory::class, 'create')]
final class ChainDriverFactoryTest extends TestCase
{
    /**
     * Проверяет, что ChainDriverFactory создаёт ChainDriver из options[drivers] с именами драйверов.
     *
     * @see ChainDriverFactory::create()
     */
    #[Test]
    public function createsChainDriver(): void
    {
        $factory = new ChainDriverFactory([new BuiltInDriverFactory()]);

        $driver = $factory->create(new CacheConfig(
            driver: 'chain',
            options: [
                'drivers' => ['array', 'array'],
            ],
        ));

        self::assertInstanceOf(ChainDriver::class, $driver);
        self::assertTrue($driver->set('a', 1));
        self::assertSame(1, $driver->get('a'));
    }

    /**
     * Проверяет, что вложенный драйвер получает свои options (каталог file-драйвера).
     *
     * @see ChainDriverFactory::create()
     * @see FileDriver::set()
     */
    #[Test]
    public function passesOptionsToNestedDrivers(): void
    {
        $directory = sys_get_temp_dir() . '/phpsoftbox-cache-test-' . bin2hex(random_bytes(6));
        $factory   = new ChainDriverFactory([new BuiltInDriverFactory()]);

        $driver = $factory->create(new CacheConfig(
            driver: 'chain',
            options: [
                'drivers' => [
                    'array',
                    ['driver' => 'file', 'options' => ['directory' => $directory]],
                ],
            ],
        ));

        self::assertTrue($driver->set('a', 1));
        self::assertCount(1, glob($directory . '/*.cache') ?: []);
    }

    /**
     * Проверяет, что без options[drivers] фабрика сообщает об ошибке конфигурации.
     *
     * @see ChainDriverFactory::create()
     */
    #[Test]
    public function missingDriversThrows(): void
    {
        $factory = new ChainDriverFactory([new BuiltInDriverFactory()]);

        $this->expectException(InvalidArgumentException::class);

        $factory->create(new CacheConfig(driver: 'chain'));
    }
}
