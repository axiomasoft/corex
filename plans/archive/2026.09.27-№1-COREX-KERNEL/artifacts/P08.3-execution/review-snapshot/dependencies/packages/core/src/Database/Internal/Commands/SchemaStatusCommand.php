<?php

declare(strict_types=1);

namespace CoreX\Database\Internal\Commands;

use CoreX\Database\Contracts\SchemaMigrator;
use CoreX\Database\Enums\SchemaScope;
use Illuminate\Console\Command;

/** @internal */
final class SchemaStatusCommand extends Command
{
    protected $signature = 'corex:schema:status {--scope=tenant : central|tenant|dedicated} {--database=}';

    protected $description = 'Show the registered CoreX schema sets and their migration status.';

    public function handle(SchemaMigrator $migrator): int
    {
        $scope = SchemaScope::tryFrom((string) $this->option('scope'));

        if ($scope === null) {
            $this->error('Unknown scope.');

            return self::INVALID;
        }
        $database = $this->option('database');
        $rows = [];
        $pending = false;
        foreach ($migrator->status($scope, is_string($database) ? $database : null) as $status) {
            $rows[] = [$status->set->id(), $status->set->scope->value, $status->set->lifecycle->value, $status->set->version, count($status->applied), count($status->pending), count($status->unknown)];
            $pending = $pending || $status->pending !== [] || $status->unknown !== [];
        }
        $this->table(['set', 'scope', 'lifecycle', 'version', 'applied', 'pending', 'unknown'], $rows);

        return $pending ? self::FAILURE : self::SUCCESS;
    }
}
