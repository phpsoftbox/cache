<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Driver\FileDriver;
use PhpSoftBox\Cache\Support\CachePruneOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_exists;
use function file_put_contents;
use function random_bytes;
use function serialize;
use function sha1;
use function sleep;
use function sys_get_temp_dir;
use function time;
use function touch;

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

    /**
     * Проверяет, что prune удаляет устаревшие, битые и временные файлы, не очищая валидный cache.
     *
     * @see FileDriver::prune()
     */
    #[Test]
    public function pruneRemovesObsoleteFilesWithoutClearingValidCache(): void
    {
        $dir = sys_get_temp_dir() . '/phpsoftbox-cache-test-' . bin2hex(random_bytes(6));
        $d   = new FileDriver($dir);

        $expiredPath = $dir . '/' . sha1('expired') . '.cache';
        $stalePath   = $dir . '/' . sha1('stale') . '.cache';
        $invalidPath = $dir . '/' . sha1('invalid') . '.cache';
        $tmpPath     = $dir . '/' . sha1('tmp') . '.cache.abc.tmp';

        self::assertTrue($d->set('valid', 'keep'));
        self::assertNotFalse(file_put_contents($expiredPath, serialize(['expiresAt' => time() - 10, 'value' => 'drop'])));
        self::assertNotFalse(file_put_contents($stalePath, serialize(['expiresAt' => null, 'value' => 'drop'])));
        self::assertNotFalse(file_put_contents($invalidPath, 'not-cache-payload'));
        self::assertNotFalse(file_put_contents($tmpPath, 'partial'));
        self::assertTrue(touch($stalePath, time() - 120));
        self::assertTrue(touch($tmpPath, time() - 120));

        $result = $d->prune(new CachePruneOptions(maxAgeSeconds: 60, temporaryMaxAgeSeconds: 60));

        self::assertSame(5, $result->scanned);
        self::assertSame(1, $result->expired);
        self::assertSame(1, $result->stale);
        self::assertSame(1, $result->invalid);
        self::assertSame(1, $result->temporary);
        self::assertSame(0, $result->failed);
        self::assertSame(4, $result->removed());

        self::assertFalse(file_exists($expiredPath));
        self::assertFalse(file_exists($stalePath));
        self::assertFalse(file_exists($invalidPath));
        self::assertFalse(file_exists($tmpPath));
        self::assertSame('keep', $d->get('valid'));
    }
}
