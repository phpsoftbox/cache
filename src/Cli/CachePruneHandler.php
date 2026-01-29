<?php

declare(strict_types=1);

namespace PhpSoftBox\Cache\Cli;

use PhpSoftBox\Cache\Contracts\CacheServiceInterface;
use PhpSoftBox\Cache\Support\CachePruneOptions;
use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;

use function is_int;
use function is_string;
use function trim;

final readonly class CachePruneHandler implements HandlerInterface
{
    public function __construct(
        private ?CacheServiceInterface $cache = null,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        if ($this->cache === null) {
            $runner->io()->writeln('Кеш не сконфигурирован (CacheServiceInterface недоступен).', 'error');

            return Response::FAILURE;
        }

        $store = $this->storeName($runner->request()->option('store'));
        if ($store === false) {
            $runner->io()->writeln('Некорректное имя cache store.', 'error');

            return Response::INVALID_INPUT;
        }

        $maxAgeSeconds = $this->maxAgeSeconds($runner->request()->option('max-age-days'));
        if ($maxAgeSeconds === false) {
            $runner->io()->writeln('Опция --max-age-days должна быть целым числом больше или равным нулю.', 'error');

            return Response::INVALID_INPUT;
        }

        $temporaryMaxAgeSeconds = $this->temporaryMaxAgeSeconds($runner->request()->option('tmp-max-age-hours'));
        if ($temporaryMaxAgeSeconds === false) {
            $runner->io()->writeln('Опция --tmp-max-age-hours должна быть целым числом больше или равным нулю.', 'error');

            return Response::INVALID_INPUT;
        }

        $result = $this->cache->prune(
            options: new CachePruneOptions(
                maxAgeSeconds: $maxAgeSeconds,
                temporaryMaxAgeSeconds: $temporaryMaxAgeSeconds,
            ),
            store: $store,
        );

        $runner->io()->table(
            ['Метрика', 'Значение'],
            [
                ['Просмотрено', $result->scanned],
                ['Удалено expired', $result->expired],
                ['Удалено stale', $result->stale],
                ['Удалено invalid', $result->invalid],
                ['Удалено tmp', $result->temporary],
                ['Ошибки удаления/чтения', $result->failed],
                ['Неподдерживаемые драйверы', $result->unsupported],
                ['Всего удалено', $result->removed()],
            ],
        );

        if ($result->failed > 0) {
            $runner->io()->writeln('Очистка кеша завершилась с ошибками.', 'error');

            return Response::FAILURE;
        }

        $runner->io()->writeln('Очистка кеша завершена.', 'success');

        return Response::SUCCESS;
    }

    private function storeName(mixed $value): string|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        return trim($value);
    }

    private function maxAgeSeconds(mixed $value): int|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_int($value) || $value < 0) {
            return false;
        }

        return $value * 86400;
    }

    private function temporaryMaxAgeSeconds(mixed $value): int|false
    {
        if ($value === null || $value === '') {
            return CachePruneOptions::DEFAULT_TEMPORARY_MAX_AGE_SECONDS;
        }

        if (!is_int($value) || $value < 0) {
            return false;
        }

        return $value * 3600;
    }
}
