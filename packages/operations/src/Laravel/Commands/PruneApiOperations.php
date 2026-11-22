<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel\Commands;

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Operations\Bulk\Internal\BulkRetention;
use CoreX\Operations\Cost\Internal\BudgetRetention;
use Illuminate\Console\Command;
use Packman\Attributes\Internal;

/**
 * Bounded retention pass for bulk results and shared budgets.
 *
 * Tenant rows are pruned only for an already restored scope. The scope payload
 * is not used to open another database.
 */
#[Internal]
final class PruneApiOperations extends Command
{
    protected $signature = 'corex:operations:prune
        {--max=100 : Maximum rows in this pass}';

    protected $description = 'Prune expired CoreX API bulk results and budget reservations.';

    public function handle(BudgetRetention $budget): int
    {
        $max = (int) $this->option('max');

        if ($max < 1 || $max > 100) {
            $this->components->error('The --max option must be between 1 and 100.');

            return self::INVALID;
        }

        $abandoned = $budget->abandonExpired($max);
        $reservations = $budget->pruneTerminal($max);
        $buckets = $budget->forgetIdleBuckets($max);

        try {
            $scope = $this->laravel->make(DataScopeFactory::class)->forCurrentPrincipal($this->laravel->make(TenantContextResolver::class)->current());
        } catch (CentralContextException|ScopeContextMismatch|ScopeStale) {
            $this->components->warn('No trusted tenant scope is active.');
            $this->components->info("Budget retention: abandoned {$abandoned}, pruned {$reservations}, idle buckets {$buckets}.");

            return self::SUCCESS;
        }

        $bulkResult = $this->laravel->make(WorkScopeRunner::class)->run(
            $scope,
            fn (): array => $this->laravel->make(BulkRetention::class)->prune(scope: $scope, limit: $max),
        );
        $this->components->info(sprintf(
            'API retention: bulk cleared %d removed %d; budget abandoned %d pruned %d idle buckets %d.',
            $bulkResult['cleared'],
            $bulkResult['removed'],
            $abandoned,
            $reservations,
            $buckets,
        ));

        return self::SUCCESS;
    }
}
