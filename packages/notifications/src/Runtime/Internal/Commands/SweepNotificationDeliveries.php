<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal\Commands;

use CoreX\Notifications\Delivery\Contracts\DeliveryExecution;
use CoreX\Notifications\Runtime\Internal\DeliveryStore;
use CoreX\Notifications\Runtime\Internal\Jobs\DeliverNotification;
use Illuminate\Console\Command;
use Packman\Attributes\Internal;

#[Internal]
final class SweepNotificationDeliveries extends Command
{
    protected $signature = 'corex:notifications:sweep
        {--limit=100 : Maximum due deliveries to dispatch}
        {--reconcile= : Reconcile one existing provider effect}';

    protected $description = 'Recover and dispatch due CoreX notification deliveries.';

    public function handle(DeliveryStore $deliveries, DeliveryExecution $execution): int
    {
        $reconcile = $this->option('reconcile');

        if (is_string($reconcile)) {
            $execution->reconcile($reconcile);

            return self::SUCCESS;
        }
        $limit = (int) $this->option('limit');

        if ($limit < 1 || $limit > 1_000) {
            $this->error('The --limit option must be between 1 and 1000.');

            return self::INVALID;
        }

        $recovered = $deliveries->recoverExpired();
        $ids = $deliveries->dueIds(limit: $limit);
        foreach ($ids as $id) {
            DeliverNotification::dispatch($id);
        }
        $this->info("Recovered {$recovered}; dispatched ".count($ids).' notification delivery job(s).');

        return self::SUCCESS;
    }
}
