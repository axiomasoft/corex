<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal\Commands;

use CoreX\Data\Data\DataScope;
use CoreX\Webhooks\Internal\WebhookDeliveryWorker;
use Illuminate\Console\Command;

/** @internal Runs a bounded delivery loop for the already restored tenant. */
final class DeliverWebhooks extends Command
{
    protected $signature = 'corex:webhooks:deliver
        {--worker=api-webhook : Lease owner for this process}
        {--max=100 : Maximum deliveries in this pass}';

    protected $description = 'Deliver due CoreX webhooks on the current trusted tenant connection.';

    public function handle(WebhookDeliveryWorker $worker): int
    {
        if (! $this->laravel->bound(DataScope::class)) {
            $this->components->warn('No trusted tenant scope is active.');

            return self::SUCCESS;
        }

        $leaseOwner = $this->option('worker');

        if (! is_string($leaseOwner)) {
            $this->components->error('The --worker option must be a string.');

            return self::INVALID;
        }
        $max = (int) $this->option('max');

        if ($max < 1 || $max > 1_000) {
            $this->components->error('The --max option must be between 1 and 1000.');

            return self::INVALID;
        }

        $scope = $this->laravel->make(DataScope::class);
        $delivered = 0;

        while ($delivered < $max && $worker->workOnce($scope, $leaseOwner) !== null) {
            $delivered++;
        }

        $this->components->info("Processed {$delivered} webhook delivery attempt(s).");

        return self::SUCCESS;
    }
}
