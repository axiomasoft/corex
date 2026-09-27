<?php

declare(strict_types=1);

namespace CoreX\Wakeup\Commands;

use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Wakeup\Contracts\DueWakeupSweeper;
use Illuminate\Console\Command;

final class SweepWakeups extends Command
{
    protected $signature = 'corex:wakeup:sweep {--limit=100 : Maximum due requests}';

    protected $description = 'Run due CoreX wakeup requests on the current trusted tenant connection.';

    public function handle(DueWakeupSweeper $sweeper, TenantContextResolver $contexts): int
    {
        $limit = (int) $this->option('limit');

        if ($limit < 1 || $limit > 1_000) {
            $this->error('The --limit option must be between 1 and 1000.');

            return self::INVALID;
        }
        $result = $sweeper->sweep($contexts->current(), $limit);
        $this->info("Claimed {$result->claimed}; completed {$result->completed}.");

        return self::SUCCESS;
    }
}
