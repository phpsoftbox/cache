<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Configurator;

use InvalidArgumentException;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Driver\ChainDriver;

use function is_array;
use function is_int;
use function is_string;

/**
 * Создаёт chain-драйвер.
 *
 * Формат options:
 *
 * - drivers: непустой список уровней (L1, L2, ...). Уровень — имя драйвера (`'array'`) или массив
 *   `['driver' => 'file', 'options' => [...]]` с опциями вложенного драйвера
 * - warmup_ttl: TTL прогрева верхних уровней, если нижний уровень не сообщает оставшийся срок записи
 *   (по умолчанию ChainDriver::DEFAULT_WARMUP_TTL)
 *
 * Пример:
 *
 * options: [
 *   'drivers' => [
 *     'array',
 *     ['driver' => 'file', 'options' => ['directory' => '/var/cache/app']],
 *   ],
 * ]
 */
final readonly class ChainDriverFactory implements DriverFactoryInterface
{
    /**
     * @param list<DriverFactoryInterface> $driverFactories фабрики вложенных драйверов
     */
    public function __construct(
        private array $driverFactories,
    ) {
    }

    public function supports(string $driver): bool
    {
        return $driver === 'chain';
    }

    public function create(CacheConfig $config): DriverInterface
    {
        $levels = $config->options['drivers'] ?? null;
        if (!is_array($levels) || $levels === []) {
            throw new InvalidArgumentException('Chain driver requires options[drivers] (non-empty list).');
        }

        $drivers = [];
        foreach ($levels as $level) {
            $drivers[] = $this->createLevel($this->levelConfig($level));
        }

        $warmupTtl = $config->options['warmup_ttl'] ?? ChainDriver::DEFAULT_WARMUP_TTL;
        if (!is_int($warmupTtl)) {
            throw new InvalidArgumentException('Chain driver options[warmup_ttl] must be an integer.');
        }

        return new ChainDriver($drivers, $warmupTtl);
    }

    private function levelConfig(mixed $level): CacheConfig
    {
        if (is_string($level) && $level !== '') {
            return new CacheConfig(driver: $level);
        }

        if (is_array($level) && is_string($level['driver'] ?? null) && $level['driver'] !== '') {
            $options = $level['options'] ?? [];
            if (!is_array($options)) {
                throw new InvalidArgumentException('Chain driver level options must be an array.');
            }

            return new CacheConfig(driver: $level['driver'], options: $options);
        }

        throw new InvalidArgumentException(
            'Chain driver options[drivers] items must be a driver name or an array with "driver" and optional "options".',
        );
    }

    private function createLevel(CacheConfig $config): DriverInterface
    {
        if ($config->driver === 'chain') {
            throw new InvalidArgumentException('Chain driver cannot contain another chain.');
        }

        foreach ($this->driverFactories as $factory) {
            if ($factory->supports($config->driver)) {
                return $factory->create($config);
            }
        }

        throw new InvalidArgumentException('Unknown cache driver in chain: ' . $config->driver);
    }
}
