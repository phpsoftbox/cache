<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Driver\RedisDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Redis;
use Throwable;

use function extension_loaded;
use function gethostbyname;

#[CoversClass(RedisDriver::class)]
final class RedisDriverTest extends TestCase
{
    /**
     * Стандартный контейнерный хост из docker-compose.
     */
    private const string REDIS_HOST = 'redis';

    /**
     * Проверяет базовый set/get на Redis.
     */
    #[Test]
    public function worksIfRedisExtensionIsAvailable(): void
    {
        if (!extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not installed.');
        }

        // В тестовой среде может не быть redis сервиса. Тогда пропускаем.
        // Имя хоста проверяем заранее: при неразрешимом имени connect() выдаёт PHP warning до исключения.
        if (gethostbyname(self::REDIS_HOST) === self::REDIS_HOST) {
            self::markTestSkipped('Redis server is not available.');
        }

        $redis = new Redis();

        try {
            $redis->connect(self::REDIS_HOST, 6379, 1.0);
        } catch (Throwable) {
            self::markTestSkipped('Redis server is not available.');
        }

        $redis->select(15);
        $redis->flushDB();

        $driver = new RedisDriver($redis);

        self::assertTrue($driver->set('a', 1, 10));
        self::assertSame(1, $driver->get('a'));
    }
}
