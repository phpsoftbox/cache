<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Driver\ArrayDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sleep;

#[CoversClass(ArrayDriver::class)]
final class ArrayDriverTest extends TestCase
{
    /**
     * Проверяет set/get/has/delete.
     */
    #[Test]
    public function basicOperationsWork(): void
    {
        $d = new ArrayDriver();

        self::assertFalse($d->has('a'));
        self::assertNull($d->get('a'));

        self::assertTrue($d->set('a', 123));
        self::assertTrue($d->has('a'));
        self::assertSame(123, $d->get('a'));

        self::assertTrue($d->delete('a'));
        self::assertFalse($d->has('a'));
    }

    /**
     * Проверяет, что ttl истекает.
     */
    #[Test]
    public function ttlExpires(): void
    {
        $d = new ArrayDriver();

        self::assertTrue($d->set('a', 'v', 1));
        self::assertSame('v', $d->get('a'));

        sleep(2);

        self::assertFalse($d->has('a'));
        self::assertNull($d->get('a'));
    }
}
