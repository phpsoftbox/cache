<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Driver\ArrayDriver;
use PhpSoftBox\Cache\Driver\ChainDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainDriver::class)]
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
}
