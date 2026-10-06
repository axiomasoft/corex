<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Config\Data\CoreOptions;
use DateInterval;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionResolverInterface;
use Psr\Clock\ClockInterface;

/**
 * Prunes `sys_pubsub_messages` rows older than a threshold — the
 * database-poll driver's message table has no built-in retention, so it
 * grows without bound unless something deletes old rows. Wire this into
 * the host's scheduler (e.g. `php artisan schedule` → daily) with
 * `--older-than` set LONGER than the worst-case downtime of any subscriber
 * reading this channel: a durable subscriber cursor (`sys_pubsub_cursors`)
 * can only resume correctly if the messages it hasn't consumed yet are
 * still in the table — pruning ahead of a stalled/down subscriber is a
 * silent, permanent invalidation loss, not just noise.
 *
 * Example scheduler wiring (host's `routes/console.php` or `AppServiceProvider`):
 * `Schedule::command('corex:messaging:prune-pubsub')->daily();`
 *
 * @internal spec: D44, P1.24, pre-mortem P1R C7
 */
final class PrunePubSubMessages extends Command
{
    protected $signature = 'corex:messaging:prune-pubsub
        {--older-than= : DateInterval-parseable age threshold, e.g. "30 days" (default: corex.pubsub.retention)}
        {--database= : Connection to prune (default: the app default)}';

    protected $description = 'Delete sys_pubsub_messages rows older than the retention threshold.';

    public function handle(ClockInterface $clock, CoreOptions $options, ConnectionResolverInterface $connections): int
    {
        /** @var string|null $olderThanOption */
        $olderThanOption = $this->option('older-than');
        $olderThan = $olderThanOption ?? $options->pubsubRetention;

        $cutoff = $clock->now()->sub(DateInterval::createFromDateString($olderThan));

        /** @var string|null $connection */
        $connection = $this->option('database');

        $deleted = $connections->connection($connection)->table('sys_pubsub_messages')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info(sprintf(
            'PubSub retention: pruned %d row(s) older than %s (threshold: %s).',
            $deleted,
            $cutoff->format(DATE_ATOM),
            $olderThan,
        ));

        return self::SUCCESS;
    }
}
