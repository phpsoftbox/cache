<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Driver\FileDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function random_bytes;
use function sleep;
use function sys_get_temp_dir;

#[CoversClass(FileDriver::class)]
final class FileDriverTest extends TestCase
{
    /**
     * Проверяет, что FileDriver умеет set/get/has/delete.
     */
    #[Test]
    public function basicOperationsWork(): void
    {
        $dir = sys_get_temp_dir() . '/phpsoftbox-cache-test-' . bin2hex(random_bytes(6));
        $d   = new FileDriver($dir);

        self::assertFalse($d->has('a'));

        self::assertTrue($d->set('a', ['x' => 1]));
        self::assertTrue($d->has('a'));
        self::assertSame(['x' => 1], $d->get('a'));

        self::assertTrue($d->delete('a'));
        self::assertFalse($d->has('a'));
    }

    /**
     * Проверяет, что ttl истекает и ключ перестаёт существовать.
     */
    #[Test]
    public function ttlExpires(): void
    {
        $dir = sys_get_temp_dir() . '/phpsoftbox-cache-test-' . bin2hex(random_bytes(6));
        $d   = new FileDriver($dir);

        self::assertTrue($d->set('a', 'v', 1));
        self::assertSame('v', $d->get('a'));

        sleep(2);

        self::assertFalse($d->has('a'));
        self::assertNull($d->get('a'));
    }
}
