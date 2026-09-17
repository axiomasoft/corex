<?php

declare(strict_types=1);

namespace CoreX\Notifications\Commands;

use CoreX\Notifications\Internal\DeliveryStore;
use CoreX\Notifications\Jobs\DeliverNotification;
use Illuminate\Console\Command;

final class SweepNotificationDeliveries extends Command
{
    protected $signature = 'corex:notifications:sweep {--limit=100 : Maximum due deliveries to dispatch}';

    protected $description = 'Recover and dispatch due CoreX notification deliveries.';

    public function handle(DeliveryStore $deliveries): int
    {
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
