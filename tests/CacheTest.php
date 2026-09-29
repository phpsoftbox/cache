<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Cache;
use PhpSoftBox\Cache\Configurator\CacheBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cache::class)]
#[CoversMethod(Cache::class, 'store')]
#[CoversMethod(Cache::class, 'pool')]
#[CoversMethod(Cache::class, 'setContextNamespace')]
#[CoversMethod(Cache::class, 'contextNamespace')]
#[CoversMethod(Cache::class, 'clear')]
final class CacheTest extends TestCase
{
    /**
     * Проверяет, что контекстный namespace изолирует данные, а его снятие возвращает исходные.
     *
     * @see Cache::setContextNamespace()
     * @see Cache::contextNamespace()
     * @see Cache::store()
     */
    #[Test]
    public function contextNamespaceIsolatesData(): void
    {
        $cache = $this->cache();
        $cache->set('foo', 'base');

        $cache->setContextNamespace('tenant.alpha');
        self::assertSame('tenant.alpha', $cache->contextNamespace());
        self::assertNull($cache->get('foo'));
        $cache->set('foo', 'tenant');

        $cache->setContextNamespace('');
        self::assertSame('base', $cache->get('foo'));
    }

    /**
     * Проверяет, что контекстный namespace действует и на PSR-6 pool сервиса.
     *
     * @see Cache::pool()
     */
    #[Test]
    public function contextNamespaceAppliesToPool(): void
    {
        $cache = $this->cache();
        $cache->setContextNamespace('tenant');
        $cache->set('foo', 'tenant');

        self::assertSame('tenant', $cache->pool()->getItem('foo')->get());
        self::assertSame('app:tenant', $cache->pool()->namespace());
    }

    /**
     * Проверяет, что clear() в контексте очищает только данные контекста.
     *
     * @see Cache::clear()
     */
    #[Test]
    public function clearInContextKeepsBaseData(): void
    {
        $cache = $this->cache();
        $cache->set('foo', 'base');
        $cache->setContextNamespace('tenant');
        $cache->set('foo', 'tenant');

        self::assertTrue($cache->clear());
        self::assertFalse($cache->has('foo'));

        $cache->setContextNamespace('');
        self::assertSame('base', $cache->get('foo'));
    }

    private function cache(): Cache
    {
        return CacheBuilder::fromConfig([
            'default' => 'default',
            'stores'  => [
                'default' => [
                    'driver'    => 'array',
                    'namespace' => 'app',
                ],
            ],
        ]);
    }
}
