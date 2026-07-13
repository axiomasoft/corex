<?php

declare(strict_types=1);

use CoreX\Contracts\DepartmentRepository;
use CoreX\Departments\DatabaseDepartmentRepository;
use CoreX\Events\DepartmentMoved;
use CoreX\Exceptions\DepartmentCycleException;
use CoreX\Models\Department as DepartmentModel;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses()->group('pg');

const DEPARTMENTS_TEST_CONNECTION = 'pgsql';
const DEPARTMENTS_WORKSPACE_1 = '00000000-0000-0000-0000-000000000201';

function departmentsPg(): Connection
{
    return DB::connection(DEPARTMENTS_TEST_CONNECTION);
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
function seedDepartment(?string $parentId, ?string $parentPath, string $name): array
{
    $id = (string) Str::uuid7();
    $label = departmentLabel($id);
    $path = $parentPath !== null && $parentPath !== '' ? $parentPath.'.'.$label : $label;

    DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->create([
        'id' => $id,
        'workspace_id' => DEPARTMENTS_WORKSPACE_1,
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

it('AC-24a: moving a subtree reparents every descendant in one UPDATE by path <@', function (): void {
    $root = seedDepartment(null, null, 'Root');
    $branchA = seedDepartment($root['id'], $root['path'], 'Branch A');
    $moving = seedDepartment($branchA['id'], $branchA['path'], 'Moving');
    $child = seedDepartment($moving['id'], $moving['path'], 'Child');
    $branchB = seedDepartment($root['id'], $root['path'], 'Branch B');

    departments()->move($moving['id'], $branchB['id']);

    $movedRow = DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->find($moving['id']);
    $childRow = DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->find($child['id']);
    $siblingRow = DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->find($branchA['id']);

    expect($movedRow->parent_id)->toBe($branchB['id'])
        ->and($movedRow->path)->toBe($branchB['path'].'.'.departmentLabel($moving['id']))
        ->and($childRow->path)->toBe($movedRow->path.'.'.departmentLabel($child['id']))
        ->and($siblingRow->path)->toBe($branchA['path']);
});

it('AC-24b: moving into its own subtree is rejected and leaves the DB unchanged', function (): void {
    $root = seedDepartment(null, null, 'Root');
    $child = seedDepartment($root['id'], $root['path'], 'Child');
    $grandchild = seedDepartment($child['id'], $child['path'], 'Grandchild');

    expect(fn () => departments()->move($root['id'], $grandchild['id']))
        ->toThrow(DepartmentCycleException::class);

    $rootRow = DepartmentModel::on(DEPARTMENTS_TEST_CONNECTION)->find($root['id']);

    expect($rootRow->path)->toBe($root['path'])
        ->and($rootRow->parent_id)->toBeNull();
});

it('AC-24c: move() dispatches DepartmentMoved with the old and new path', function (): void {
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

it('creates a GIST index and a unique constraint on sys_departments.path', function (): void {
    $indexes = DB::connection(DEPARTMENTS_TEST_CONNECTION)
        ->select("select indexname, indexdef from pg_indexes where tablename = 'sys_departments'");

    $byName = collect($indexes)->keyBy('indexname');

    expect($byName->has('sys_dept_path_gist'))->toBeTrue()
        ->and($byName->get('sys_dept_path_gist')->indexdef)->toContain('USING gist')
        ->and($byName->has('sys_dept_path_uq'))->toBeTrue();
});
