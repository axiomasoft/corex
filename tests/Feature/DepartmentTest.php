<?php

declare(strict_types=1);

use CoreX\Contracts\DepartmentRepository;
use CoreX\Departments\DatabaseDepartmentRepository;
use CoreX\Events\DepartmentMoved;
use CoreX\Exceptions\CrossWorkspaceMoveException;
use CoreX\Exceptions\DepartmentCycleException;
use CoreX\Models\Department as DepartmentModel;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses()->group('pg');

const DEPARTMENTS_TEST_CONNECTION = 'pgsql';
const DEPARTMENTS_WORKSPACE_1 = '00000000-0000-0000-0000-000000000201';
const DEPARTMENTS_WORKSPACE_2 = '00000000-0000-0000-0000-000000000202';

function departmentsPg(): Connection
{
    return DB::connection(DEPARTMENTS_TEST_CONNECTION);
}

/** A second, independent session on the same pg database — models a concurrent caller. */
function departmentsSession(string $name): Connection
{
    /** @var array<string, mixed> $config */
    $config = config('database.connections.'.DEPARTMENTS_TEST_CONNECTION);
    config(['database.connections.'.$name => $config]);

    return DB::connection($name);
}

function departments(): DepartmentRepository
{
    return new DatabaseDepartmentRepository(connection: DEPARTMENTS_TEST_CONNECTION);
}

function departmentLabel(string $id): string
{
    return str_replace('-', '', $id);
}

/** @return array{id:string,path:string} */
function seedDepartment(?string $parentId, ?string $parentPath, string $name, string $workspaceId = DEPARTMENTS_WORKSPACE_1): array
{
    $id = (string) Str::uuid7();
    $label = departmentLabel($id);
    $path = $parentPath !== null && $parentPath !== '' ? $parentPath.'.'.$label : $label;

    DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->create([
        'id' => $id,
        'workspace_id' => $workspaceId,
        'parent_id' => $parentId,
        'path' => $path,
        'name' => $name,
    ]);

    return ['id' => $id, 'path' => $path];
}

beforeEach(function (): void {
    // D12: sys_departments/sys_department_user are PG-only (ltree/GIST).
    // Reset to a clean public schema, same pattern as SettingsTest/AuditLogTest/FeatureFlagsTest.
    departmentsPg()->statement('DROP SCHEMA IF EXISTS public CASCADE');
    departmentsPg()->statement('CREATE SCHEMA public');
    DB::purge(DEPARTMENTS_TEST_CONNECTION);

    $this->artisan('migrate', [
        '--database' => DEPARTMENTS_TEST_CONNECTION,
        '--path' => realpath(__DIR__.'/../../packages/core/database/migrations/pg'),
        '--realpath' => true,
        '--force' => true,
    ])->run();
});

it('AC-24a: reparents the whole subtree in EXACTLY ONE UPDATE (query count, A25/A27)', function (): void {
    $root = seedDepartment(null, null, 'Root');
    $branchA = seedDepartment($root['id'], $root['path'], 'Branch A');
    $moving = seedDepartment($branchA['id'], $branchA['path'], 'Moving');
    $child = seedDepartment($moving['id'], $moving['path'], 'Child');
    $branchB = seedDepartment($root['id'], $root['path'], 'Branch B');

    // Count UPDATEs against sys_departments during the move — a recursive /
    // per-row (N+1) rewrite would issue more than one and turn this red, which
    // the old "compare final paths" assertion could not distinguish (A25/A27).
    $updates = [];
    DB::listen(function ($query) use (&$updates): void {
        if ($query->connectionName === DEPARTMENTS_TEST_CONNECTION
            && preg_match('/^\s*update\s+sys_departments\b/i', $query->sql) === 1) {
            $updates[] = $query->sql;
        }
    });

    departments()->move($moving['id'], $branchB['id']);

    expect($updates)->toHaveCount(1);

    $movedRow = DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->find($moving['id']);
    $childRow = DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->find($child['id']);
    $siblingRow = DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->find($branchA['id']);

    expect($movedRow->parent_id)->toBe($branchB['id'])
        ->and($movedRow->path)->toBe($branchB['path'].'.'.departmentLabel($moving['id']))
        ->and($childRow->path)->toBe($movedRow->path.'.'.departmentLabel($child['id']))
        ->and($siblingRow->path)->toBe($branchA['path']);
});

it('AC-24b: move() waits on the workspace advisory lock — a holder blocks it, another workspace does not', function (): void {
    $root = seedDepartment(null, null, 'Root');
    $childA = seedDepartment($root['id'], $root['path'], 'A');
    $childB = seedDepartment($root['id'], $root['path'], 'B');

    // A separate session holds THIS workspace's advisory xact lock inside an
    // open transaction; a probe on yet another session sees it locked.
    $holder = departmentsSession('pgsql_dept_holder');
    $holder->beginTransaction();
    expect($holder->selectOne('select pg_try_advisory_xact_lock(hashtext(?)) as ok', ['sys_departments:'.DEPARTMENTS_WORKSPACE_1])->ok)->toBeTrue();

    $probe = departmentsSession('pgsql_dept_probe');
    expect($probe->selectOne('select pg_try_advisory_xact_lock(hashtext(?)) as ok', ['sys_departments:'.DEPARTMENTS_WORKSPACE_1])->ok)->toBeFalse()
        // A different workspace key is NOT blocked — the lock is workspace-scoped.
        ->and($probe->selectOne('select pg_try_advisory_xact_lock(hashtext(?)) as ok', ['sys_departments:'.DEPARTMENTS_WORKSPACE_2])->ok)->toBeTrue();

    // move() must WAIT on that lock: with a short lock_timeout it aborts instead
    // of proceeding — proof it takes the lock (remove lock() and this completes).
    departmentsPg()->statement("set lock_timeout = '500ms'");
    expect(fn () => departments()->move($childA['id'], $childB['id']))->toThrow(QueryException::class);

    $holder->rollBack();

    // The blocked move rolled back entirely — nothing moved.
    $row = DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->find($childA['id']);
    expect($row->path)->toBe($childA['path']);
});

it('AC-24c: a committed move() dispatches DepartmentMoved with the old and new path', function (): void {
    Event::fake([DepartmentMoved::class]);

    $root = seedDepartment(null, null, 'Root');
    $branchA = seedDepartment($root['id'], $root['path'], 'Branch A');
    $branchB = seedDepartment($root['id'], $root['path'], 'Branch B');

    departments()->move($branchA['id'], $branchB['id']);

    Event::assertDispatched(
        DepartmentMoved::class,
        fn (DepartmentMoved $event): bool => $event->departmentId === $branchA['id']
            && $event->oldPath === $branchA['path']
            && $event->newPath === $branchB['path'].'.'.departmentLabel($branchA['id'])
            && $event::name() === 'core.department.moved',
    );
});

it('AC-24c (after-commit): a move rolled back by an outer transaction emits NO event (A6)', function (): void {
    // Laravel's Foundation testing manager runs afterCommit callbacks at level 1
    // (it assumes a RefreshDatabase wrapping transaction); these PG tests migrate
    // by hand and have no such wrapper, so swap in the PRODUCTION manager to
    // exercise the real level-0 afterCommit-vs-rollback semantics move() ships.
    departmentsPg()->setTransactionManager(new DatabaseTransactionsManager);

    $root = seedDepartment(null, null, 'Root');
    $branchA = seedDepartment($root['id'], $root['path'], 'Branch A');

    // The move dispatches via afterCommit; wrapped in an outer transaction that
    // rolls back, the event must NOT leak — consumers (dept_tree invalidation,
    // P2.9) would otherwise act on a subtree that never committed. If event()
    // fired inside the transaction instead, this assertion would catch it.
    Event::fake([DepartmentMoved::class]);

    try {
        departmentsPg()->transaction(function () use ($branchA, $root): void {
            departments()->move($branchA['id'], $root['id']);

            throw new RuntimeException('force rollback');
        });
    } catch (RuntimeException) {
        // expected
    }

    Event::assertNotDispatched(DepartmentMoved::class);
});

it('AC-24d: a second primary department for the same user is rejected (partial-unique)', function (): void {
    $deptA = seedDepartment(null, null, 'A');
    $deptB = seedDepartment(null, null, 'B');
    $userId = (string) Str::uuid7();

    DB::connection(DEPARTMENTS_TEST_CONNECTION)->table('sys_department_user')->insert([
        'department_id' => $deptA['id'],
        'user_id' => $userId,
        'is_primary' => true,
    ]);

    expect(fn () => DB::connection(DEPARTMENTS_TEST_CONNECTION)->table('sys_department_user')->insert([
        'department_id' => $deptB['id'],
        'user_id' => $userId,
        'is_primary' => true,
    ]))->toThrow(QueryException::class);
});

it('AC-24e: subtree() and primaryOf() return the correct rows', function (): void {
    $root = seedDepartment(null, null, 'Root');
    $child = seedDepartment($root['id'], $root['path'], 'Child');
    $other = seedDepartment(null, null, 'Other');
    $userId = (string) Str::uuid7();

    DB::connection(DEPARTMENTS_TEST_CONNECTION)->table('sys_department_user')->insert([
        'department_id' => $child['id'],
        'user_id' => $userId,
        'is_primary' => true,
    ]);

    $subtree = departments()->subtree($root['id']);

    expect($subtree)->toHaveCount(2)
        ->and(array_map(fn ($department) => $department->id, $subtree))->toEqualCanonicalizing([$root['id'], $child['id']])
        ->and(departments()->primaryOf($userId)?->id)->toBe($child['id'])
        ->and(departments()->find($other['id'])?->name)->toBe('Other');
});

it('AC-24 (cycle): moving into its own subtree is rejected and leaves the DB unchanged (A26)', function (): void {
    $root = seedDepartment(null, null, 'Root');
    $child = seedDepartment($root['id'], $root['path'], 'Child');
    $grandchild = seedDepartment($child['id'], $child['path'], 'Grandchild');

    expect(fn () => departments()->move($root['id'], $grandchild['id']))
        ->toThrow(DepartmentCycleException::class);

    $rootRow = DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->find($root['id']);

    expect($rootRow->path)->toBe($root['path'])
        ->and($rootRow->parent_id)->toBeNull();
});

it('AC-24 (root move): move($id, null) reparents the subtree to the workspace root (A26)', function (): void {
    $root = seedDepartment(null, null, 'Root');
    $child = seedDepartment($root['id'], $root['path'], 'Child');
    $grand = seedDepartment($child['id'], $child['path'], 'Grand');

    departments()->move($child['id'], null);

    $childRow = DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->find($child['id']);
    $grandRow = DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->find($grand['id']);

    expect($childRow->parent_id)->toBeNull()
        ->and($childRow->path)->toBe(departmentLabel($child['id']))
        ->and($grandRow->path)->toBe(departmentLabel($child['id']).'.'.departmentLabel($grand['id']));
});

it('AC-24 (soft-delete): a removed department drops out of find/subtree/primaryOf (A24)', function (): void {
    $root = seedDepartment(null, null, 'Root');
    $child = seedDepartment($root['id'], $root['path'], 'Child');
    $userId = (string) Str::uuid7();

    DB::connection(DEPARTMENTS_TEST_CONNECTION)->table('sys_department_user')->insert([
        'department_id' => $child['id'],
        'user_id' => $userId,
        'is_primary' => true,
    ]);

    DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->find($child['id'])->delete();

    expect(departments()->find($child['id']))->toBeNull()
        ->and(array_map(fn ($d) => $d->id, departments()->subtree($root['id'])))->toBe([$root['id']])
        ->and(departments()->primaryOf($userId))->toBeNull();
});

it('AC-24 (re-read guard): move() on a soft-deleted department throws instead of a silent no-op (A5 If-then)', function (): void {
    $root = seedDepartment(null, null, 'Root');
    $target = seedDepartment(null, null, 'Target');
    $moving = seedDepartment($root['id'], $root['path'], 'Moving');

    DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->find($moving['id'])->delete();

    expect(fn () => departments()->move($moving['id'], $target['id']))
        ->toThrow(ModelNotFoundException::class);

    // The row is untouched (still soft-deleted, original path) — no no-op UPDATE ran.
    $raw = DB::connection(DEPARTMENTS_TEST_CONNECTION)->table('sys_departments')->where('id', $moving['id'])->first();
    expect($raw->path)->toBe($moving['path'])
        ->and($raw->deleted_at)->not->toBeNull();
});

it('AC-24 (workspace scope): subtree() excludes a same-path row from another workspace', function (): void {
    $root = seedDepartment(null, null, 'Root');

    // A row in ANOTHER workspace whose ltree path is a descendant of root's path
    // (paths are globally unique but the contract is per-workspace, B-10 §5.7).
    $intruderId = (string) Str::uuid7();
    DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->create([
        'id' => $intruderId,
        'workspace_id' => DEPARTMENTS_WORKSPACE_2,
        'parent_id' => null,
        'path' => $root['path'].'.'.departmentLabel($intruderId),
        'name' => 'Intruder',
    ]);

    $subtree = departments()->subtree($root['id']);

    expect(array_map(fn ($d) => $d->id, $subtree))->toBe([$root['id']]);
});

it('AC-24 (workspace scope): move() rewrites ONLY the moving workspace, never a same-path row in another (D63/D67)', function (): void {
    $root = seedDepartment(null, null, 'Root');
    $target = seedDepartment(null, null, 'Target');

    // A row in ANOTHER workspace whose ltree path is a descendant of root's
    // (globally-unique paths, per-workspace contract — B-10 §5.7). The
    // `path <@ root.path` predicate matches it too; without the workspace_id
    // filter, move()'s ONE-UPDATE would rewrite this foreign row's path.
    $intruderId = (string) Str::uuid7();
    $intruderPath = $root['path'].'.'.departmentLabel($intruderId);
    DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->create([
        'id' => $intruderId,
        'workspace_id' => DEPARTMENTS_WORKSPACE_2,
        'parent_id' => null,
        'path' => $intruderPath,
        'name' => 'Intruder',
    ]);

    departments()->move($root['id'], $target['id']);

    $intruder = DB::connection(DEPARTMENTS_TEST_CONNECTION)
        ->table('sys_departments')->where('id', $intruderId)->first();

    // The foreign-workspace row is untouched — same path, same (null) parent.
    expect($intruder->path)->toBe($intruderPath)
        ->and($intruder->parent_id)->toBeNull();
});

it('AC-24 (workspace scope): move() into a parent in ANOTHER workspace is rejected and moves nothing (P1.29 M-2, symmetric to D67)', function (): void {
    $moving = seedDepartment(null, null, 'Moving', DEPARTMENTS_WORKSPACE_1);

    // A parent that lives in a DIFFERENT workspace. Its ltree path is globally
    // unique, so the cycle check and the FK layer pass — only the parent-side
    // workspace guard stops the cross-tenant reparent (the symmetric hole to
    // the descendant-side D67 filter).
    $foreignParent = seedDepartment(null, null, 'Foreign parent', DEPARTMENTS_WORKSPACE_2);

    expect(fn () => departments()->move($moving['id'], $foreignParent['id']))
        ->toThrow(CrossWorkspaceMoveException::class);

    $row = DB::connection(DEPARTMENTS_TEST_CONNECTION)
        ->table('sys_departments')->where('id', $moving['id'])->first();

    // Untouched: still a root in its own workspace, no cross-tenant reparent.
    expect($row->path)->toBe($moving['path'])
        ->and($row->parent_id)->toBeNull()
        ->and($row->workspace_id)->toBe(DEPARTMENTS_WORKSPACE_1);
});

it('AC-24 (DTO): the read-DTO carries sort_order', function (): void {
    $id = (string) Str::uuid7();
    DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->create([
        'id' => $id,
        'workspace_id' => DEPARTMENTS_WORKSPACE_1,
        'parent_id' => null,
        'path' => departmentLabel($id),
        'name' => 'Ordered',
        'sort_order' => 7,
    ]);

    expect(departments()->find($id)?->sortOrder)->toBe(7);
});

it('creates a GIST index and a unique constraint on sys_departments.path', function (): void {
    $indexes = DB::connection(DEPARTMENTS_TEST_CONNECTION)
        ->select("select indexname, indexdef from pg_indexes where tablename = 'sys_departments'");

    $byName = collect($indexes)->keyBy('indexname');

    expect($byName->has('sys_dept_path_gist'))->toBeTrue()
        ->and($byName->get('sys_dept_path_gist')->indexdef)->toContain('USING gist')
        ->and($byName->has('sys_dept_path_uq'))->toBeTrue();
});
