<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Configurator\BuiltInDriverFactory;
use PhpSoftBox\Cache\Configurator\CacheConfig;
use PhpSoftBox\Cache\Configurator\CacheStoreFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheStoreFactory::class)]
#[CoversMethod(CacheStoreFactory::class, 'store')]
final class CacheStoreFactoryTest extends TestCase
{
    /**
     * Проверяет, что PSR-16 и PSR-6 одного стора работают поверх одного драйвера: записанное через PSR-16
     * видно через PSR-6 (для array-драйвера раньше создавались два независимых хранилища).
     *
     * @see CacheStoreFactory::store()
     */
    #[Test]
    public function psr16AndPsr6ShareDriver(): void
    {
        $factory = new CacheStoreFactory(
            stores: ['default' => new CacheConfig(driver: 'array', namespace: 'app')],
            driverFactories: [new BuiltInDriverFactory()],
        );

        $factory->store()->psr16()->set('a', 1);

        self::assertSame(1, $factory->store()->psr6()->getItem('a')->get());
    }

    /**
     * Проверяет, что повторный запрос стора отдаёт тот же объект (драйвер не пересоздаётся).
     *
     * @see CacheStoreFactory::store()
     */
    #[Test]
    public function storeIsCreatedOnce(): void
    {
        $factory = new CacheStoreFactory(
            stores: ['default' => new CacheConfig(driver: 'array')],
            driverFactories: [new BuiltInDriverFactory()],
        );

        self::assertSame($factory->store(), $factory->store());
    }
}
