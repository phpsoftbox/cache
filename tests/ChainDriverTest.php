<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Driver\ArrayDriver;
use PhpSoftBox\Cache\Driver\ChainDriver;
use PhpSoftBox\Cache\Tests\Fixture\ExpirationUnawareDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sleep;
use function time;

#[CoversClass(ChainDriver::class)]
#[CoversMethod(ChainDriver::class, 'fetch')]
#[CoversMethod(ChainDriver::class, 'set')]
#[CoversMethod(ChainDriver::class, 'clear')]
#[CoversMethod(ChainDriver::class, 'prune')]
final class ChainDriverTest extends TestCase
{
    /**
     * Проверяет, что chain прогревает верхний уровень при hit на нижнем.
     */
    #[Test]
    public function fetchWarmsUpUpperLevel(): void
    {
        $l1 = new ArrayDriver();
        $l2 = new ArrayDriver();

        $l2->set('a', 1);

        $chain = new ChainDriver([$l1, $l2]);

        self::assertSame(1, $chain->get('a'));

        // теперь значение должно оказаться в l1
        self::assertSame(1, $l1->get('a'));
    }

    /**
     * Проверяет, что set записывает во все уровни.
     */
    #[Test]
    public function setWritesToAllLevels(): void
    {
        $l1 = new ArrayDriver();
        $l2 = new ArrayDriver();

        $chain = new ChainDriver([$l1, $l2]);

        self::assertTrue($chain->set('k', 'v'));

        self::assertSame('v', $l1->get('k'));
        self::assertSame('v', $l2->get('k'));
    }

    /**
     * Проверяет, что ChainDriver делегирует prune всем уровням cache chain.
     *
     * @see ChainDriver::prune()
     */
    #[Test]
    public function pruneDelegatesToAllLevels(): void
    {
        $l1 = new ArrayDriver();
        $l2 = new ArrayDriver();

        $l1->set('a', 'v', 1);
        $l2->set('b', 'v', 1);

        sleep(2);

        $result = new ChainDriver([$l1, $l2])->prune();

        self::assertSame(2, $result->scanned);
        self::assertSame(2, $result->expired);
        self::assertFalse($l1->has('a'));
        self::assertFalse($l2->has('b'));
    }

    /**
     * Проверяет, что прогрев верхнего уровня получает оставшийся TTL записи нижнего уровня.
     *
     * @see ChainDriver::fetch()
     */
    #[Test]
    public function fetchWarmsUpperLevelWithRemainingTtl(): void
    {
        $l1 = new ArrayDriver();
        $l2 = new ArrayDriver();

        $l2->set('a', 1, 100);

        new ChainDriver([$l1, $l2])->get('a');

        $expiresAt = $l1->fetchWithExpiration('a')['expiresAt'];
        self::assertSame($l2->fetchWithExpiration('a')['expiresAt'], $expiresAt);
    }

    /**
     * Проверяет, что при неизвестном сроке записи (драйвер без ExpirationAwareDriverInterface) прогрев идёт
     * с warmupTtl, а не навсегда.
     *
     * @see ChainDriver::fetch()
     */
    #[Test]
    public function fetchWarmsUpperLevelWithWarmupTtlWhenExpirationUnknown(): void
    {
        $l1 = new ArrayDriver();
        $l2 = new ExpirationUnawareDriver();

        $l2->set('a', 1);

        new ChainDriver([$l1, $l2], warmupTtl: 30)->get('a');

        $expiresAt = $l1->fetchWithExpiration('a')['expiresAt'];
        self::assertGreaterThanOrEqual(time() + 29, $expiresAt);
        self::assertLessThanOrEqual(time() + 30, $expiresAt);
    }

    /**
     * Проверяет, что clear() с namespace очищает namespace на всех уровнях и не трогает остальные ключи.
     *
     * @see ChainDriver::clear()
     */
    #[Test]
    public function clearWithNamespaceClearsAllLevels(): void
    {
        $l1 = new ArrayDriver();
        $l2 = new ArrayDriver();

        $chain = new ChainDriver([$l1, $l2]);

        $chain->set('app:a', 1);
        $chain->set('other:b', 2);

        self::assertTrue($chain->clear('app'));

        self::assertFalse($l1->has('app:a'));
        self::assertFalse($l2->has('app:a'));
        self::assertTrue($l1->has('other:b'));
        self::assertTrue($l2->has('other:b'));
    }
}
