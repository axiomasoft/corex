<?php

declare(strict_types=1);

namespace CoreX\Api\Commands;

use CoreX\Api\Internal\Bulk\BulkRetention;
use CoreX\Api\Internal\Cost\BudgetRetention;
use CoreX\Api\Internal\Webhook\WebhookRetention;
use CoreX\Support\Data\DataScope;
use Illuminate\Console\Command;

/**
 * Bounded retention pass for bulk results, webhook history and shared budgets.
 *
 * Tenant rows are pruned only for an already restored scope. The scope payload
 * is not used to open another database.
 *
 * @internal
 */
final class PruneApiOperations extends Command
{
    protected $signature = 'corex:api:prune
        {--max=100 : Maximum rows in this pass}';

    protected $description = 'Prune expired CoreX API bulk results, webhook history and budget reservations.';

    public function handle(BulkRetention $bulk, WebhookRetention $webhooks, BudgetRetention $budget): int
    {
        $max = (int) $this->option('max');

        if ($max < 1 || $max > 100) {
            $this->components->error('The --max option must be between 1 and 100.');

            return self::INVALID;
        }

        $abandoned = $budget->abandonExpired($max);
        $reservations = $budget->pruneTerminal($max);
        $buckets = $budget->forgetIdleBuckets($max);

        if (! $this->laravel->bound(DataScope::class)) {
            $this->components->warn('No trusted tenant scope is active.');
            $this->components->info("Budget retention: abandoned {$abandoned}, pruned {$reservations}, idle buckets {$buckets}.");

            return self::SUCCESS;
        }

        $scope = $this->laravel->make(DataScope::class);
        $bulkResult = $bulk->prune($scope, $max);
        $webhookResult = $webhooks->prune($scope, $max);
        $this->components->info(sprintf(
            'API retention: bulk cleared %d removed %d; webhook bodies %d attempts %d inbox %d; budget abandoned %d pruned %d idle buckets %d.',
            $bulkResult['cleared'],
            $bulkResult['removed'],
            $webhookResult['bodies'],
            $webhookResult['attempts'],
            $webhookResult['inbox'],
            $abandoned,
            $reservations,
            $buckets,
        ));

        return self::SUCCESS;
    }
}
