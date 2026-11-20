<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal\Commands;

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Webhooks\Internal\WebhookDeliveryWorker;
use Illuminate\Console\Command;
use Packman\Attributes\Internal;

/** @internal Runs a bounded delivery loop for the already restored tenant. */
#[Internal]
final class DeliverWebhooks extends Command
{
    protected $signature = 'corex:webhooks:deliver
        {--worker=api-webhook : Lease owner for this process}
        {--max=100 : Maximum deliveries in this pass}';

    protected $description = 'Deliver due CoreX webhooks on the current trusted tenant connection.';

    public function handle(): int
    {
        try {
            $scope = $this->laravel->make(DataScopeFactory::class)->forCurrentPrincipal($this->laravel->make(TenantContextResolver::class)->current());
        } catch (CentralContextException|ScopeContextMismatch) {
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

        $delivered = 0;
        $runner = $this->laravel->make(WorkScopeRunner::class);

        while ($delivered < $max && $runner->run($scope, fn () => $this->laravel->make(WebhookDeliveryWorker::class)->workOnce(scope: $scope, worker: $leaseOwner)) !== null) {
            $delivered++;
        }

        $this->components->info("Processed {$delivered} webhook delivery attempt(s).");

        return self::SUCCESS;
    }
}
