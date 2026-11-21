<?php

declare(strict_types=1);

namespace CoreX\Foundation\Laravel;

use Closure;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ReferenceOwners;
use CoreX\Foundation\Data\CleanupAction;
use CoreX\Foundation\Data\LifecycleBlocker;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Foundation\Internal\DatabaseLifecyclePlans;
use CoreX\Foundation\Internal\LifecycleCheckpoint;
use CoreX\Foundation\Internal\LifecycleFrame;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\ApplicationLocator;

/** Keyset pagination over the declaring package's own tables, projected onto owner witnesses. */
#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final class SqlReferenceInventory
{
    /**
     * @param  array<string,array{Connection,list<string>,2?:(Closure(array<string,mixed>,string): (LifecycleBlocker|CleanupAction|null))|null,3?:Closure(Builder, DataScope):void}>  $tables  Table name maps to its verified connection and provenance columns.
     */
    public static function scan(string $owner, array $tables, ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $revision = hash('sha256', json_encode([$owner, array_keys($tables), $module, $scope], JSON_THROW_ON_ERROR));
        $position = $after === null ? [0, []] : json_decode($after->position, true, flags: JSON_THROW_ON_ERROR);

        if (($after !== null && $after->revision !== $revision) || ! is_array($position) || count($position) !== 2
            || ! is_int($position[0]) || ! is_array($position[1]) || $position[0] < 0 || $position[0] >= count($tables)) {
            throw new LifecycleBlocked('Invalid owner reference cursor.');
        }
        [$index, $last] = $position;
        $last = array_values($last);
        $names = array_keys($tables);
        $table = $names[$index];
        [$connection, $fields] = $tables[$table];
        $schema = $connection->getSchemaBuilder();
        $columns = $schema->getColumnListing($table);
        $app = ApplicationLocator::current();

        if ($columns !== [] && isset($app->make(LifecycleFrame::class)->purges[$owner])) {
            throw new LifecycleBlocked('Owner table exists after its confirmed native purge.');
        }

        if ($columns === [] && $after === null && $connection->getDriverName() === 'pgsql' && array_all($tables, static fn (array $entry): bool => $entry[0] === $connection)) {
            $proof = $app->make(DatabaseLifecyclePlans::class)->droppedSchema($scope, $owner, $names, $connection);

            return new ReferencePage([], hash('sha256', $revision.$proof));
        }

        if ($columns === [] || array_diff($fields, $columns) !== []) {
            throw new LifecycleBlocked('Missing owner schema for '.$table);
        }
        $unique = [];
        foreach ($schema->getIndexes($table) as $constraint) {
            if ($constraint['primary'] || ($unique === [] && $constraint['unique'])) {
                $unique = $constraint['columns'];
            }
        }

        if ($unique === [] || ($last !== [] && count($unique) !== count($last))) {
            throw new LifecycleBlocked('Unverified reference ordering for '.$table);
        }
        $query = $connection->table($table)->useWritePdo()->select(array_unique([...$unique, ...$fields]));

        if (in_array('account_id', $columns, true)) {
            $query->whereRaw('CAST(account_id AS TEXT) = ?', [$scope->accountId]);
        }

        if (in_array('account_incarnation', $columns, true)) {
            $query->where('account_incarnation', $scope->accountIncarnation);
        }

        if (isset($tables[$table][3])) {
            $tables[$table][3]($query, $scope);
        } elseif (in_array('workspace_scope', $columns, true)) {
            $query->where('workspace_scope', $scope->workspaceId === null ? 'account' : 'workspace:'.$scope->workspaceId);
        } elseif (in_array('workspace_scope_key', $columns, true) && ! in_array('workspace_scope', $columns, true)) {
            $query->where('workspace_scope_key', $scope->workspaceId === null ? 'account' : 'workspace:'.$scope->workspaceId);
        } elseif (in_array('workspace_id', $columns, true)) {
            if ($scope->workspaceId === null) {
                $query->whereNull('workspace_id');
            } else {
                $query->whereRaw('CAST(workspace_id AS TEXT) = ?', [$scope->workspaceId]);
            }
        } elseif ($scope->workspaceId !== null) {
            return self::next([], $revision, $index + 1, [], count($tables));
        }

        foreach ($unique as $column) {
            $query->orderBy($column);
        }

        if ($last !== []) {
            $position = $last;
            $query->where(static function (Builder $after) use ($unique, $position): void {
                foreach ($unique as $index => $column) {
                    $after->orWhere(static function (Builder $branch) use ($unique, $position, $index, $column): void {
                        foreach (array_slice($unique, 0, $index) as $prior => $matched) {
                            $branch->where($matched, $position[$prior]);
                        }
                        $branch->where($column, '>', $position[$index]);
                    });
                }
            });
        }
        $rows = $query->limit(65)->get();
        $blockers = [];
        $actions = [];
        foreach ($rows->take(64) as $row) {
            $record = LifecycleCheckpoint::map(get_object_vars($row));
            $last = array_map(static fn (string $column): mixed => $record[$column], $unique);

            if (in_array(null, $last, true)) {
                throw new LifecycleBlocked('Reference has no stable owner identity.');
            }
            $id = count($last) === 1 && (is_string($last[0]) || is_int($last[0])) ? (string) $last[0] : hash('sha256', json_encode($last, JSON_THROW_ON_ERROR));
            $witness = array_intersect_key($record, array_flip($fields));
            // Historical and opaque formats require the declaring host to resolve their
            // installed provenance from the original record, including child references.
            $required = 'remove-or-rebind-reference';

            if ($module->package !== $owner) {
                if (($witness['module'] ?? null) === $module->package) {
                    // The owner persisted the canonical module identity.
                } elseif ($app->bound(ReferenceOwners::class)) {
                    $refs = $app->make(ReferenceOwners::class)->resolve($scope, $owner, $table, ['record' => $id, ...$witness]);

                    if (! array_any($refs, static fn (ModuleRef $target): bool => $target->package === $module->package)) {
                        continue;
                    }
                } else {
                    $required = 'verify-historical-provenance';
                }
            }

            if (isset($tables[$table][2]) && $required !== 'verify-historical-provenance') {
                $projection = $tables[$table][2]($record, $id);

                if ($projection instanceof CleanupAction) {
                    $actions[] = $projection;
                } elseif ($projection instanceof LifecycleBlocker) {
                    $blockers[] = $projection;
                }
            } else {
                $blockers[] = new LifecycleBlocker($owner, $table, $id, $scope, $required);
            }
        }

        return $rows->count() > 64
            ? self::next($blockers, $revision, $index, $last, count($tables), $actions)
            : self::next($blockers, $revision, $index + 1, [], count($tables), $actions);
    }

    /** @param list<LifecycleBlocker> $blockers
     * @param list<mixed> $last
     * @param list<CleanupAction> $actions */
    private static function next(array $blockers, string $revision, int $index, array $last, int $count, array $actions = []): ReferencePage
    {
        $next = $index < $count ? new ReferenceCursor(json_encode([$index, $last], JSON_THROW_ON_ERROR), $revision) : null;

        return new ReferencePage($blockers, $revision, $next, $actions);
    }
}
