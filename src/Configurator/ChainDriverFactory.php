<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Configurator;

use InvalidArgumentException;
use PhpSoftBox\Cache\Contracts\DriverInterface;
use PhpSoftBox\Cache\Driver\ChainDriver;

use function is_array;
use function is_string;

/**
 * Создаёт chain-драйвер.
 *
 * Ожидаемый формат options:
 *
 * - stores: non-empty-list<string> (имена driver для вложенных уровней)
 *
 * Пример:
 *
 * options: [
 *   'stores' => ['array', 'file']
 * ]
 */
final readonly class ChainDriverFactory implements DriverFactoryInterface
{
    /**
     * @param list<DriverFactoryInterface> $driverFactories
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
        /** @var mixed $stores */
        $stores = $config->options['stores'] ?? null;
        if (!is_array($stores) || $stores === []) {
            throw new InvalidArgumentException('Chain driver требует options[stores] (non-empty list).');
        }

        $drivers = [];
        foreach ($stores as $storeDriverName) {
            if (!is_string($storeDriverName) || $storeDriverName === '') {
                throw new InvalidArgumentException('Chain driver options[stores] должен быть списком строк.');
            }

            $drivers[] = $this->createByName($storeDriverName);
        }

        if ($drivers === []) {
            throw new InvalidArgumentException('Chain driver требует хотя бы один драйвер в options[stores].');
        }

        return new ChainDriver($drivers);
    }

    private function createByName(string $driver): DriverInterface
    {
        foreach ($this->driverFactories as $factory) {
            if ($factory->supports($driver)) {
                return $factory->create(new CacheConfig(driver: $driver));
            }
        }

        throw new InvalidArgumentException('Unknown cache driver in chain: ' . $driver);
    }
}
