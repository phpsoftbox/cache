<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Tests;

use PhpSoftBox\Cache\Driver\RedisDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Redis;
use Throwable;

use function extension_loaded;
use function gethostbyname;
use function time;

#[CoversClass(RedisDriver::class)]
#[CoversMethod(RedisDriver::class, 'set')]
#[CoversMethod(RedisDriver::class, 'fetch')]
#[CoversMethod(RedisDriver::class, 'fetchWithExpiration')]
#[CoversMethod(RedisDriver::class, 'delete')]
#[CoversMethod(RedisDriver::class, 'setMultiple')]
#[CoversMethod(RedisDriver::class, 'clear')]
final class RedisDriverTest extends TestCase
{
    /**
     * Стандартный контейнерный хост из docker-compose.
     */
    private const string REDIS_HOST = 'redis';

    private Redis $redis;

    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not installed.');
        }

        // Имя хоста проверяем заранее: при неразрешимом имени connect() выдаёт PHP warning до исключения.
        if (gethostbyname(self::REDIS_HOST) === self::REDIS_HOST) {
            self::markTestSkipped('Redis server is not available.');
        }

        $this->redis = new Redis();

        try {
            $this->redis->connect(self::REDIS_HOST, 6379, 1.0);
        } catch (Throwable) {
            self::markTestSkipped('Redis server is not available.');
        }

        $this->redis->select(15);
        $this->redis->flushDB();
    }

    /**
     * Проверяет базовый set/get на Redis.
     *
     * @see RedisDriver::set()
     * @see RedisDriver::get()
     */
    #[Test]
    public function setAndGetWork(): void
    {
        $driver = new RedisDriver($this->redis);

        self::assertTrue($driver->set('a', 1, 10));
        self::assertSame(1, $driver->get('a'));
    }

    /**
     * Проверяет, что закешированный null — hit.
     *
     * @see RedisDriver::fetch()
     */
    #[Test]
    public function nullValueIsHit(): void
    {
        $driver = new RedisDriver($this->redis);

        $driver->set('a', null);

        self::assertSame(['hit' => true, 'value' => null], $driver->fetch('a'));
    }

    /**
     * Проверяет, что TTL 0 удаляет существующую запись, а не сохраняет её на секунду.
     *
     * @see RedisDriver::set()
     */
    #[Test]
    public function zeroTtlDeletesExistingKey(): void
    {
        $driver = new RedisDriver($this->redis);

        $driver->set('a', 'v');

        self::assertTrue($driver->set('a', 'new', 0));

        self::assertFalse($driver->has('a'));
    }

    /**
     * Проверяет, что удаление отсутствующего ключа — не ошибка.
     *
     * @see RedisDriver::delete()
     */
    #[Test]
    public function deleteMissingKeyReturnsTrue(): void
    {
        $driver = new RedisDriver($this->redis);

        self::assertTrue($driver->delete('missing'));
    }

    /**
     * Проверяет, что setMultiple с TTL выставляет срок жизни каждому ключу.
     *
     * @see RedisDriver::setMultiple()
     */
    #[Test]
    public function setMultipleWithTtlSetsExpiration(): void
    {
        $driver = new RedisDriver($this->redis);

        self::assertTrue($driver->setMultiple(['a' => 1, 'b' => 2], 100));

        self::assertSame(['a' => 1, 'b' => 2], $driver->getMultiple(['a', 'b']));
        self::assertGreaterThan(0, $this->redis->ttl('a'));
        self::assertGreaterThan(0, $this->redis->ttl('b'));
    }

    /**
     * Проверяет, что fetchWithExpiration отдаёт момент истечения записи.
     *
     * @see RedisDriver::fetchWithExpiration()
     */
    #[Test]
    public function fetchWithExpirationReturnsExpiresAt(): void
    {
        $driver = new RedisDriver($this->redis);

        $driver->set('a', 'v', 100);
        $driver->set('b', 'v');

        $expiresAt = $driver->fetchWithExpiration('a')['expiresAt'];

        self::assertGreaterThanOrEqual(time() + 98, $expiresAt);
        self::assertLessThanOrEqual(time() + 100, $expiresAt);
        self::assertSame(['hit' => true, 'value' => 'v', 'expiresAt' => null], $driver->fetchWithExpiration('b'));
    }

    /**
     * Проверяет, что clear() с namespace удаляет только ключи namespace и не трогает чужие данные базы.
     *
     * @see RedisDriver::clear()
     */
    #[Test]
    public function clearWithNamespaceKeepsForeignKeys(): void
    {
        $driver = new RedisDriver($this->redis);

        $driver->set('app:a', 1);
        $driver->set('app:sub:b', 2);
        $driver->set('apple:c', 3);
        $this->redis->set('session:1', 'foreign');

        self::assertTrue($driver->clear('app'));

        self::assertFalse($driver->has('app:a'));
        self::assertFalse($driver->has('app:sub:b'));
        self::assertTrue($driver->has('apple:c'));
        self::assertSame('foreign', $this->redis->get('session:1'));
    }

    /**
     * Проверяет, что glob-символы в namespace сравниваются буквально.
     *
     * @see RedisDriver::clear()
     */
    #[Test]
    public function clearTreatsGlobCharactersLiterally(): void
    {
        $driver = new RedisDriver($this->redis);

        $driver->set('a*:x', 1);
        $driver->set('ab:y', 2);

        self::assertTrue($driver->clear('a*'));

        self::assertFalse($driver->has('a*:x'));
        self::assertTrue($driver->has('ab:y'));
    }
}
