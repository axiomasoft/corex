<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal\Commands;

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Operations\Bulk\Internal\BulkWorker;
use Illuminate\Console\Command;
use Packman\Attributes\Internal;

/** @internal Runs one leased bulk step in the already restored tenant. */
#[Internal]
final class WorkBulkOperations extends Command
{
    protected $signature = 'corex:operations:bulk-work {--worker=api-bulk : Lease owner for one bounded step}';

    protected $description = 'Claim and advance one durable bulk operation';

    public function handle(): int
    {
        try {
            $scope = $this->laravel->make(DataScopeFactory::class)->forCurrentPrincipal($this->laravel->make(TenantContextResolver::class)->current());
        } catch (CentralContextException|ScopeContextMismatch) {
            $this->components->warn('No trusted tenant scope is active.');

            return self::SUCCESS;
        }

        $this->laravel->make(WorkScopeRunner::class)->run($scope, fn () => $this->laravel->make(BulkWorker::class)->workOnce($scope));

        return self::SUCCESS;
    }
}
