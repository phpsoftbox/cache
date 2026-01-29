<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Cli;

use PhpSoftBox\CliApp\Command\Command;
use PhpSoftBox\CliApp\Command\CommandRegistryInterface;
use PhpSoftBox\CliApp\Command\OptionDefinition;
use PhpSoftBox\CliApp\Loader\CommandProviderInterface;

final class CacheCommandProvider implements CommandProviderInterface
{
    public function register(CommandRegistryInterface $registry): void
    {
        $registry->register(Command::define(
            name: 'cache:prune',
            description: 'Удалить устаревшие записи кеша без полного clear()',
            signature: [
                new OptionDefinition(
                    name: 'store',
                    short: 's',
                    description: 'Имя cache store',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'max-age-days',
                    short: null,
                    description: 'Удалять permanent-записи старше N дней (по умолчанию не удаляются)',
                    required: false,
                    default: null,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'tmp-max-age-hours',
                    short: null,
                    description: 'Удалять временные cache-файлы старше N часов',
                    required: false,
                    default: 24,
                    type: 'int',
                ),
            ],
            handler: CachePruneHandler::class,
        ));
    }
}
