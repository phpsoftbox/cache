<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use DateTimeImmutable;
use PhpSoftBox\Cache\Driver\ArrayDriver;
use PhpSoftBox\Cache\Psr6\CacheItemPool;
use PhpSoftBox\Cache\Psr6\InvalidKeyException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheItemPool::class)]
#[CoversMethod(CacheItemPool::class, 'getItem')]
#[CoversMethod(CacheItemPool::class, 'getItems')]
#[CoversMethod(CacheItemPool::class, 'save')]
#[CoversMethod(CacheItemPool::class, 'clear')]
final class CacheItemPoolTest extends TestCase
{
    /**
     * Проверяет, что закешированный null — hit.
     *
     * @see CacheItemPool::getItem()
     */
    #[Test]
    public function cachedNullIsHit(): void
    {
        $pool = new CacheItemPool(new ArrayDriver());

        $pool->save($pool->getItem('a')->set(null));

        $item = $pool->getItem('a');

        self::assertTrue($item->isHit());
        self::assertNull($item->get());
    }

    /**
     * Проверяет, что getItems() тоже считает закешированный null попаданием, а отсутствующий ключ — промахом.
     *
     * @see CacheItemPool::getItems()
     */
    #[Test]
    public function getItemsDistinguishesNullFromMiss(): void
    {
        $pool = new CacheItemPool(new ArrayDriver());

        $pool->save($pool->getItem('a')->set(null));

        $items = $pool->getItems(['a', 'b']);

        self::assertTrue($items['a']->isHit());
        self::assertFalse($items['b']->isHit());
    }

    /**
     * Проверяет, что PSR-6 отклоняет ключи с зарезервированными символами (`:` — разделитель namespace).
     *
     * @see CacheItemPool::getItem()
     */
    #[Test]
    public function reservedCharactersInKeyThrow(): void
    {
        $pool = new CacheItemPool(new ArrayDriver(), namespace: 'app');

        $this->expectException(InvalidKeyException::class);

        $pool->getItem('a:b');
    }

    /**
     * Проверяет, что save() с expiresAt в прошлом удаляет существующую запись.
     *
     * @see CacheItemPool::save()
     */
    #[Test]
    public function expiresAtInPastDeletesItem(): void
    {
        $pool = new CacheItemPool(new ArrayDriver());

        $pool->save($pool->getItem('a')->set('v'));

        $item = $pool->getItem('a')->set('new')->expiresAt(new DateTimeImmutable('-1 minute'));
        self::assertTrue($pool->save($item));

        self::assertFalse($pool->hasItem('a'));
    }

    /**
     * Проверяет, что clear() очищает только свой namespace.
     *
     * @see CacheItemPool::clear()
     */
    #[Test]
    public function clearKeepsOtherNamespaces(): void
    {
        $driver = new ArrayDriver();

        $app   = new CacheItemPool($driver, namespace: 'app');
        $other = new CacheItemPool($driver, namespace: 'other');
        $app->save($app->getItem('a')->set(1));
        $other->save($other->getItem('a')->set(2));

        self::assertTrue($app->clear());

        self::assertFalse($app->hasItem('a'));
        self::assertSame(2, $other->getItem('a')->get());
    }
}
