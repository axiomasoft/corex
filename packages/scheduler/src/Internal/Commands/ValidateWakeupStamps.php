<?php

declare(strict_types=1);

namespace CoreX\Scheduler\Internal\Commands;

use CoreX\Scheduler\Internal\Value;
use CoreX\Scheduler\Internal\WakeupHandlerRegistry;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use JsonException;

/** @internal Operator command; its CLI signature is the supported surface. */
final class ValidateWakeupStamps extends Command
{
    protected $signature = 'corex:scheduler:validate-stamps {--limit=1000 : Maximum pending or running requests to inspect}';

    protected $description = 'Report incompatible durable wakeup handler stamps without changing requests.';

    public function handle(Connection $connection, WakeupHandlerRegistry $handlers): int
    {
        $limit = (int) $this->option('limit');

        if ($limit < 1 || $limit > 10_000) {
            $this->error('The --limit option must be between 1 and 10000.');

            return self::INVALID;
        }

        $rows = $connection->table('sys_wakeup_requests')
            ->whereIn('status', ['pending', 'running'])
            ->oldest('fire_at')
            ->orderBy('id')
            ->limit($limit + 1)
            ->get(['kind', 'request_key', 'status', 'payload']);
        $truncated = $rows->count() > $limit;
        $rows = $rows->take($limit);
        $issues = [];

        foreach ($rows as $row) {
            try {
                $payload = json_decode(Value::text($row->payload), true, flags: JSON_THROW_ON_ERROR);
                $inspection = $handlers->inspectPayload(kind: Value::text($row->kind), payload: $payload);
                $issue = $inspection['status'];
            } catch (JsonException) {
                $issue = 'handler-stamp-missing';
            }

            if ($issue === 'ready') {
                continue;
            }

            $issues[] = [Value::text($row->kind), Value::text($row->request_key), Value::text($row->status), $issue];
        }

        if ($issues === [] && ! $truncated) {
            $this->info("Validated {$rows->count()} pending/running wakeup requests; no incompatible stamps found.");

            return self::SUCCESS;
        }

        if ($issues !== []) {
            $this->table(['Kind', 'Request key', 'Status', 'Issue'], $issues);
            $this->error(sprintf('Found %d wakeup request(s) requiring operator review; no rows were changed.', count($issues)));
        }

        if ($truncated) {
            $this->error("Inspection stopped at --limit={$limit}; additional pending/running rows remain unvalidated.");
        }

        return self::FAILURE;
    }
}
