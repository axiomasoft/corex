<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal\Commands;

use CoreX\Config\Data\CoreOptions;
use CoreX\Messaging\Internal\BatchedDelete;
use DateInterval;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionResolverInterface;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

/**
 * Удаляет терминальные строки sys_outbox_messages старше порога.
 *
 * Два окна, а не одно: опубликованные записи нужны сверке, мёртвые — расследованию,
 * и второе обычно длиннее. Строки в pending и publishing не удаляются никогда — это
 * недоставленная работа, а не история.
 *
 * sys_inbox_messages эта команда НЕ трогает: удаление отметки дедупликации заново
 * открывает окно дублей (research/P5-design.md §7.3, D29).
 *
 * @internal spec: P5.15 — console command is not a supported API
 */
#[Internal]
final class PruneOutboxMessages extends Command
{
    protected $signature = 'corex:messaging:prune
        {--published-older-than= : DateInterval-parseable age for published rows (default: corex.messaging.outbox_retention)}
        {--dead-older-than= : DateInterval-parseable age for dead rows (default: corex.messaging.dead_retention)}
        {--database= : Connection to prune (default: the app default)}
        {--batch= : Rows deleted per statement (default: 1000)}';

    protected $description = 'Delete terminal sys_outbox_messages rows older than their retention window.';

    public function handle(ClockInterface $clock, CoreOptions $options, ConnectionResolverInterface $connections): int
    {
        /** @var string|null $publishedOption */
        $publishedOption = $this->option('published-older-than');
        /** @var string|null $deadOption */
        $deadOption = $this->option('dead-older-than');
        /** @var string|null $connection */
        $connection = $this->option('database');

        $published = $publishedOption ?? $options->messagingOutboxRetention;
        $dead = $deadOption ?? $options->messagingDeadRetention;
        $batch = BatchedDelete::batchOption($this->option('batch'));
        $now = $clock->now();
        $table = 'sys_outbox_messages';

        $prunedPublished = BatchedDelete::run(query: $connections->connection($connection)->table($table)
            ->where('status', 'published')
            ->where('published_at', '<', $now->sub(DateInterval::createFromDateString($published))), key: 'id', batch: $batch);

        $prunedDead = BatchedDelete::run(query: $connections->connection($connection)->table($table)
            ->where('status', 'dead')
            ->where('dead_at', '<', $now->sub(DateInterval::createFromDateString($dead))), key: 'id', batch: $batch);

        $this->info(sprintf(
            'Outbox retention: pruned %d published (older than %s) and %d dead (older than %s).',
            $prunedPublished,
            $published,
            $prunedDead,
            $dead,
        ));

        return self::SUCCESS;
    }
}
