<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Laravel;

use CoreX\CustomFields\Schemas\Internal\SchemaWorker;
use Illuminate\Console\Command;
use Packman\Attributes\Internal;

#[Internal]
final class RunFieldSchemaWorker extends Command
{
    protected $signature = 'corex:custom-fields:work {--limit=1 : Maximum bounded schema phases or backfill batches}';

    protected $description = 'Advance durable field schema operations in the current trusted service scope';

    public function handle(SchemaWorker $worker): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);

        if ($limit === false) {
            return self::INVALID;
        }
        $count = 0;
        while ($count < $limit && $worker->one()) {
            $count++;
        }
        $this->line('Processed schema phases: '.$count);

        return self::SUCCESS;
    }
}
