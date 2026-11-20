<?php

declare(strict_types=1);

namespace CoreX\Workflow\Execution\Laravel\Commands;

use CoreX\Workflow\Execution\Internal\StartIntentWorker;
use Illuminate\Console\Command;
use Packman\Attributes\Internal;

#[Internal]
final class SweepWorkflowIntents extends Command
{
    protected $signature = 'corex:workflow:sweep {--limit=100 : Maximum runnable intents to claim}';

    protected $description = 'Start or reconcile durable workflow intents for the current account.';

    public function handle(StartIntentWorker $worker): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);

        if ($limit === false) {
            $this->error('The --limit option must be between 1 and 1000.');

            return self::INVALID;
        }

        $this->info('Inspected '.$worker->sweep($limit).' workflow intent(s).');

        return self::SUCCESS;
    }
}
