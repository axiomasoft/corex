<?php

declare(strict_types=1);

namespace CoreX\Api\Commands;

use CoreX\Api\Internal\Bulk\BulkWorker;
use CoreX\Support\Data\DataScope;
use Illuminate\Console\Command;

/** @internal Runs one leased bulk step in the already restored tenant. */
final class WorkBulkOperations extends Command
{
    protected $signature = 'corex:bulk-work {--worker=api-bulk : Lease owner for one bounded step}';

    protected $description = 'Claim and advance one durable bulk operation';

    public function handle(BulkWorker $worker): int
    {
        if (! $this->laravel->bound(DataScope::class)) {
            $this->components->warn('No trusted tenant scope is active.');

            return self::SUCCESS;
        }

        $worker->workOnce($this->laravel->make(DataScope::class));

        return self::SUCCESS;
    }
}
