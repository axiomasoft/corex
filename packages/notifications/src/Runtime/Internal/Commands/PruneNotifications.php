<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal\Commands;

use CoreX\Notifications\Runtime\Internal\NotificationRetention;
use DateInterval;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final class PruneNotifications extends Command
{
    protected $signature = 'corex:notifications:prune
        {--older-than-days= : Minimum age of the read, dismissal and cancellation timestamps (default: corex-notifications.retention.settled_days)}
        {--database= : Verified tenant connection (default: the app default)}
        {--batch=1000 : Maximum read notifications examined per transaction}';

    protected $description = 'Prune expired read notifications in the active account and workspace.';

    public function handle(ClockInterface $clock, DatabaseManager $connections, NotificationRetention $retention): int
    {
        $days = $this->positive(name: 'older-than-days', fallback: $retention->defaultRetentionDays);
        $batch = $this->positive(name: 'batch', fallback: 1000);

        if ($days === null || $batch === null || $batch > 10_000) {
            $this->error('--older-than-days must be a positive integer and --batch between 1 and 10000.');

            return self::INVALID;
        }
        /** @var string|null $name */
        $name = $this->option('database');
        $connection = $connections->connection($name);
        $now = $clock->now();
        $pruned = $retention->prune(connection: $connection, now: $now, cutoff: $now->sub(new DateInterval('P'.$days.'D')), batch: $batch);

        $this->info(sprintf('Notification retention: pruned %d read notification(s) older than %d day(s).', $pruned, $days));

        return self::SUCCESS;
    }

    private function positive(string $name, int $fallback): ?int
    {
        $value = $this->option($name);

        if ($value === null) {
            return $fallback;
        }

        return is_string($value) && preg_match('/^[1-9][0-9]{0,8}$/D', $value) === 1 ? (int) $value : null;
    }
}
