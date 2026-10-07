<?php

declare(strict_types=1);

namespace CoreX\Database\Internal\Commands;

use CoreX\Database\Contracts\SchemaMigrator;
use CoreX\Database\Enums\SchemaScope;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/** @internal */
final class SchemaMigrateCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'corex:schema:migrate {--scope=tenant : central|tenant|dedicated} {--database=} {--modules} {--force}';

    protected $description = 'Run the registered CoreX schema sets of one scope.';

    public function handle(SchemaMigrator $migrator): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }
        $scope = SchemaScope::tryFrom((string) $this->option('scope'));

        if ($scope === null) {
            $this->error('Unknown scope.');

            return self::INVALID;
        }
        $database = $this->option('database');
        $ran = $migrator->migrate(scope: $scope, connection: is_string($database) ? $database : null, includeModules: $this->option('modules'));
        $this->line($ran === [] ? 'Nothing to migrate.' : implode(PHP_EOL, $ran));

        return self::SUCCESS;
    }
}
