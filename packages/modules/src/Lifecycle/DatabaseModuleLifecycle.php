<?php

declare(strict_types=1);

namespace Flex\Modules\Lifecycle;

use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\TenantContext;
use Flex\Modules\Contracts\ModuleLifecycle;
use Flex\Modules\Contracts\ModuleRegistry;
use Flex\Modules\Contracts\RecordsRegistrar;
use Flex\Modules\Discovery\ModuleDiscovery;
use Flex\Modules\Enums\DisableReason;
use Flex\Modules\Enums\ModuleState;
use Flex\Modules\Events\ModuleArchived;
use Flex\Modules\Events\ModuleDisabled;
use Flex\Modules\Events\ModuleEnabled;
use Flex\Modules\Events\ModulePurged;
use Flex\Modules\Events\ModuleUpgraded;
use Flex\Modules\Exceptions\HasEnabledDependentsException;
use Flex\Modules\Exceptions\MissingDependencyException;
use Flex\Modules\Exceptions\ModuleNotFound;
use Flex\Modules\Manifest;
use Flex\Modules\ModuleContext;
use Flex\Modules\ModuleStatus;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/**
 * DB-backed {@see ModuleLifecycle} (B-10 §3.3/§5.1/§5.3/§5.4). Every
 * transition is wrapped in `pg_advisory_xact_lock(hashtext($module))`
 * (xact-scoped, released on commit — B-10 §7.3 п.3): a second concurrent
 * caller blocks on the lock, then sees the already-applied state and no-ops
 * (AC-11).
 *
 * Migration atomicity (AC-7): the whole transition runs inside ONE
 * `$this->db()->transaction()`. Laravel's migrator wraps each migration in its
 * own (nested → savepoint) transaction; a failing migration K rolls back only
 * its own savepoint and rethrows. This class catches that here, records the
 * failure, and lets the outer transaction commit anyway — migrations
 * 1..K-1 persist as real, already-applied rows, `mod_modules` is left
 * untouched, and a retry continues at K.
 *
 * `$codeVersions`/`$migrationPaths` are constructor seams (composer name →
 * value) standing in for what real deploys would source from
 * `installed.json` and each module's `database/migrations` directory.
 * Wiring those from real `CorexModule` classes is deferred — see
 * phases/P1.md P1.6 Pending Work (mirrors the P1.5 precedent of an
 * injectable seam over full cross-item wiring).
 */
final class DatabaseModuleLifecycle implements ModuleLifecycle
{
    private const MODULES_TABLE = 'mod_modules';

    private const RECORDS_TABLE = 'mod_records';

    private const LOG_TABLE = 'mod_lifecycle_log';

    /**
     * @param  array<string, string>  $codeVersions
     * @param  array<string, string>  $migrationPaths
     * @param  ?string  $connection  null = the app's default connection (D12: production is PG-only, so that's correct there; tests pin an explicit name to avoid ambient-default mutation).
     */
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleDiscovery $discovery,
        private readonly RecordsRegistrar $records,
        private readonly Container $container,
        private readonly TenantContextResolver $tenantContextResolver,
        private readonly array $codeVersions = [],
        private readonly array $migrationPaths = [],
        private readonly ?string $connection = null,
    ) {}

    private function db(): Connection
    {
        return DB::connection($this->connection);
    }

    /**
     * Microsecond-precision timestamp string. Laravel's query-builder date
     * format is second-precision (Grammar::getDateFormat()) — passing a
     * bare now() Carbon instance as a bound value truncates it, and
     * multiple rows written in the same wall-clock second (e.g. two claims
     * from one enable()) become unorderable by created_at (AC-10 purge
     * order, AC-13 log order). A pre-formatted string bypasses that
     * reformat; the timestamptz column itself is microsecond-precision.
     */
    private function timestamp(): string
    {
        return now()->format('Y-m-d H:i:s.u');
    }

    public function enable(string $module, string $edition = 'standard', bool $cascade = false): void
    {
        $compiled = $this->registry->get($module); // ModuleNotFound if not a deployed module
        $tenant = $this->tenantContextResolver->current();

        $this->db()->transaction(function () use ($module, $edition, $cascade, $tenant, $compiled): void {
            $this->lock($module);

            $current = $this->currentRow($module);

            if ($current !== null && $current->state === ModuleState::Enabled->value) {
                return; // AC-11: lock serialized us behind the winner — no-op.
            }

            foreach ($compiled->requires as $dep) {
                if ($dep['type'] === 'module') {
                    $this->ensureDependencyEnabled($module, $dep['target'], $cascade);
                }
            }

            $fromState = $current === null ? ModuleState::Available->value : $current->state;
            $context = new ModuleContext($module, $tenant, $this->records);

            $migration = $this->runModuleMigrations($module);

            if ($migration['error'] === null) {
                try {
                    $this->invokeHook($this->manifestFor($module)->onEnableHook, $context);
                } catch (Throwable $e) {
                    $migration['error'] = $e;
                }
            }

            if ($migration['error'] instanceof Throwable) {
                $this->log($module, 'enable', $fromState, $fromState, 'failed', [
                    'migrations_run' => $migration['ran'],
                    'error' => $migration['error']->getMessage(),
                ]);

                return; // 1..K-1 stay applied; mod_modules untouched; retry continues at K.
            }

            $this->upsertModuleRow($module, $current, ModuleState::Enabled, $edition, $migration['ran']);

            $this->log($module, 'enable', $fromState, ModuleState::Enabled->value, 'success', [
                'migrations_run' => $migration['ran'],
            ]);

            $this->db()->afterCommit(static fn () => event(new ModuleEnabled($module, $edition, $migration['ran'])));
        });
    }

    public function disable(string $module, DisableReason $reason, bool $cascade = false): void
    {
        $tenant = $this->tenantContextResolver->current();

        $this->db()->transaction(function () use ($module, $reason, $cascade, $tenant): void {
            $this->lock($module);

            $current = $this->currentRow($module);

            if ($current === null || $current->state !== ModuleState::Enabled->value) {
                return; // nothing enabled to disable — no-op.
            }

            $dependents = $this->enabledDependents($module);

            if ($dependents !== [] && ! $cascade) {
                throw new HasEnabledDependentsException($module, $dependents);
            }

            foreach ($dependents as $dependent) {
                $this->disable($dependent, DisableReason::Dependency, cascade: true);
            }

            $context = new ModuleContext($module, $tenant, $this->records);
            $this->invokeHook($this->manifestFor($module)->onDisableHook, $context);

            $this->db()->table(self::MODULES_TABLE)->where('name', $module)->update([
                'state' => ModuleState::Disabled->value,
                'disable_reason' => $reason->value,
                'disabled_at' => $this->timestamp(),
                'updated_at' => $this->timestamp(),
            ]);

            $this->log($module, 'disable', ModuleState::Enabled->value, ModuleState::Disabled->value, 'success', [
                'reason' => $reason->value,
            ]);

            $this->db()->afterCommit(static fn () => event(new ModuleDisabled($module, $reason)));
        });
    }

    public function uninstall(string $module, bool $keepData = true): void
    {
        $tenant = $this->tenantContextResolver->current();

        $this->db()->transaction(function () use ($module, $keepData, $tenant): void {
            $this->lock($module);

            $current = $this->currentRow($module);

            if ($current === null) {
                return; // nothing to uninstall.
            }

            if (! in_array($current->state, [ModuleState::Disabled->value, ModuleState::Archived->value], true)) {
                throw new LogicException(sprintf(
                    'Module [%s] must be disabled before uninstall (current state: %s) — B-10 §5.1 two-step safety.',
                    $module,
                    $current->state,
                ));
            }

            if ($keepData) {
                $this->db()->table(self::MODULES_TABLE)->where('name', $module)->update([
                    'state' => ModuleState::Archived->value,
                    'updated_at' => $this->timestamp(),
                ]);

                $this->log($module, 'archive', $current->state, ModuleState::Archived->value, 'success', []);

                $this->db()->afterCommit(static fn () => event(new ModuleArchived($module)));

                return;
            }

            $this->purge($module, $current->state, $tenant);
        });
    }

    public function upgrade(string $module): void
    {
        $this->db()->transaction(function () use ($module): void {
            $this->lock($module);

            $current = $this->currentRow($module);

            if ($current === null) {
                throw new ModuleNotFound($module);
            }

            $migration = $this->runModuleMigrations($module);
            $codeVersion = $this->codeVersion($module);

            $this->db()->table(self::MODULES_TABLE)->where('name', $module)->update([
                'version' => $migration['error'] === null ? $codeVersion : $current->version,
                'schema_version' => $migration['ran'] !== [] ? end($migration['ran']) : $current->schema_version,
                'updated_at' => $this->timestamp(),
            ]);

            $this->log($module, 'upgrade', $current->state, $current->state, $migration['error'] === null ? 'success' : 'failed', [
                'migrations_run' => $migration['ran'],
                'from_version' => $current->version,
                'to_version' => $codeVersion,
                'error' => $migration['error']?->getMessage(),
            ]);

            if ($migration['error'] === null) {
                $fromVersion = $current->version;
                $this->db()->afterCommit(static fn () => event(new ModuleUpgraded($module, $fromVersion, $codeVersion, $migration['ran'])));
            }
        });
    }

    public function status(string $module): ModuleStatus
    {
        $current = $this->currentRow($module);
        $codeVersion = $this->codeVersion($module);

        if ($current === null) {
            return new ModuleStatus($module, ModuleState::Available, $codeVersion, null, false, 'standard');
        }

        return new ModuleStatus(
            name: $module,
            state: ModuleState::from($current->state),
            codeVersion: $codeVersion,
            dbVersion: $current->version,
            pendingUpgrade: version_compare($codeVersion, $current->version, '>'),
            edition: $current->edition,
        );
    }

    // ─── Internals ──────────────────────────────────────────────────────

    private function lock(string $module): void
    {
        $this->db()->statement('select pg_advisory_xact_lock(hashtext(?))', [$module]);
    }

    private function currentRow(string $module): ?object
    {
        return $this->db()->table(self::MODULES_TABLE)->where('name', $module)->first();
    }

    private function ensureDependencyEnabled(string $module, string $target, bool $cascade): void
    {
        $targetState = $this->currentRow($target)?->state;

        if ($targetState === ModuleState::Enabled->value) {
            return;
        }

        if (! $cascade) {
            throw new MissingDependencyException($module, $target);
        }

        $this->enable($target, cascade: true);
    }

    /** @return list<string> */
    private function enabledDependents(string $module): array
    {
        $dependents = [];

        foreach ($this->discovery->discover() as $manifest) {
            foreach ($manifest->requires as $dep) {
                if ($dep->type !== 'module' || $dep->target !== $module) {
                    continue;
                }

                if ($this->currentRow($manifest->composerName)?->state === ModuleState::Enabled->value) {
                    $dependents[] = $manifest->composerName;
                }

                break;
            }
        }

        return $dependents;
    }

    private function purge(string $module, string $fromState, TenantContext $tenant): void
    {
        $context = new ModuleContext($module, $tenant, $this->records);
        $this->invokeHook($this->manifestFor($module)->onPurgeHook, $context);

        // mod_records has no surrogate id to break created_at ties (two claims
        // in the same migration transaction can land in the same wall-clock
        // microsecond); `reference DESC` makes the drop order deterministic
        // instead of undefined when that happens — real deploys where
        // claims are seconds/minutes apart never hit the tiebreak.
        /** @var Collection<int, string> $tables */
        $tables = $this->db()->table(self::RECORDS_TABLE)
            ->where('module_name', $module)
            ->where('record_type', 'table')
            ->orderByDesc('created_at')
            ->orderByDesc('reference')
            ->pluck('reference');

        foreach ($tables as $table) {
            Schema::connection($this->connection)->dropIfExists($table);
        }

        $this->db()->table(self::RECORDS_TABLE)->where('module_name', $module)->delete();

        $this->db()->table(self::MODULES_TABLE)->where('name', $module)->update([
            'state' => ModuleState::Purged->value,
            'updated_at' => $this->timestamp(),
        ]);

        $this->log($module, 'purge', $fromState, ModuleState::Purged->value, 'success', [
            'dropped_tables' => $tables->all(),
        ]);

        $droppedTables = $tables->all();
        $this->db()->afterCommit(static fn () => event(new ModulePurged($module, $droppedTables)));
    }

    private function invokeHook(?string $hookClass, ModuleContext $context): void
    {
        if ($hookClass === null) {
            return;
        }

        /** @var callable(ModuleContext): void $hook */
        $hook = $this->container->make($hookClass);
        $hook($context);
    }

    private function manifestFor(string $module): Manifest
    {
        foreach ($this->discovery->discover() as $manifest) {
            if ($manifest->composerName === $module) {
                return $manifest;
            }
        }

        throw new ModuleNotFound($module);
    }

    /**
     * Runs every pending migration under the module's registered migrations
     * path (if any) via Laravel's own migrator/repository, so 1..K-1 are
     * tracked exactly like any other Laravel migration — see class docblock
     * for why that survives a failing K under our wrapping transaction.
     *
     * @return array{ran: list<string>, error: ?Throwable}
     */
    private function runModuleMigrations(string $module): array
    {
        $path = $this->migrationPaths[$module] ?? null;

        if ($path === null) {
            return ['ran' => [], 'error' => null];
        }

        /** @var Migrator $migrator */
        $migrator = $this->container->make('migrator');

        return $migrator->usingConnection($this->connection, function () use ($migrator, $path): array {
            $repository = $migrator->getRepository();

            if (! $repository->repositoryExists()) {
                $repository->createRepository();
            }

            $before = $repository->getRan();
            $error = null;

            try {
                $migrator->run([$path]);
            } catch (Throwable $e) {
                $error = $e;
            }

            $after = $repository->getRan();

            return ['ran' => array_values(array_diff($after, $before)), 'error' => $error];
        });
    }

    private function codeVersion(string $module): string
    {
        return $this->codeVersions[$module] ?? '0.0.0';
    }

    /** @param  list<string>  $migrationsRun */
    private function upsertModuleRow(string $module, ?object $current, ModuleState $state, string $edition, array $migrationsRun): void
    {
        $now = $this->timestamp();
        $schemaVersion = $migrationsRun !== [] ? end($migrationsRun) : ($current->schema_version ?? null);

        if ($current !== null) {
            $this->db()->table(self::MODULES_TABLE)->where('name', $module)->update([
                'state' => $state->value,
                'version' => $this->codeVersion($module),
                'schema_version' => $schemaVersion,
                'edition' => $edition,
                'enabled_at' => $now,
                'disabled_at' => null,
                'disable_reason' => null,
                'updated_at' => $now,
            ]);

            return;
        }

        $this->db()->table(self::MODULES_TABLE)->insert([
            'id' => (string) Str::uuid7(),
            'name' => $module,
            'state' => $state->value,
            'version' => $this->codeVersion($module),
            'schema_version' => $schemaVersion,
            'edition' => $edition,
            'enabled_at' => $now,
            'disabled_at' => null,
            'disable_reason' => null,
            'config' => json_encode([], JSON_THROW_ON_ERROR),
            'meta' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param  array<string, mixed>  $details */
    private function log(string $module, string $operation, string $from, string $to, string $status, array $details): void
    {
        $this->db()->table(self::LOG_TABLE)->insert([
            'id' => (string) Str::uuid7(),
            'module_name' => $module,
            'operation' => $operation,
            'from_state' => $from,
            'to_state' => $to,
            'status' => $status,
            'actor_type' => 'system', // interim: no P2 auth actor propagation yet — see Pending Work.
            'actor_id' => null,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'created_at' => $this->timestamp(),
            'updated_at' => $this->timestamp(),
        ]);
    }
}
