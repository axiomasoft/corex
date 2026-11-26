<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses()->group('pg');

/**
 * @return array<string, mixed>
 */
function autColumn(string $table, string $column): array
{
    /** @var array<string, mixed>|null $row */
    $row = DB::table('information_schema.columns')
        ->where('table_name', $table)
        ->where('column_name', $column)
        ->select(['data_type', 'is_nullable', 'column_default'])
        ->first();

    expect($row)->not->toBeNull("column {$table}.{$column} must exist");

    return (array) $row;
}

function autConstraintDef(string $constraint): string
{
    /** @var string|null $def */
    $def = DB::selectOne('select pg_get_constraintdef(oid) as def from pg_constraint where conname = ?', [$constraint])?->def;

    expect($def)->not->toBeNull("constraint {$constraint} must exist");

    return (string) $def;
}

// boxed: tenant/ carries no cross-package FK, must apply on
// a schema that never had users/wsp_workspaces.
it('applies the tenant/ aut_* migrations on a clean schema without users or wsp_workspaces', function (): void {
    $this->resetPgSchema();
    $this->migrateAutSchema();

    expect(Schema::hasTable('aut_roles'))->toBeTrue()
        ->and(Schema::hasTable('aut_role_matrix'))->toBeTrue()
        ->and(Schema::hasTable('aut_role_assignments'))->toBeTrue()
        ->and(Schema::hasTable('aut_direct_grants'))->toBeTrue();
});

// DDL parity against the access schema contract.
it('matches DB_SCHEMA §3.5 aut_roles column-by-column', function (): void {
    $this->resetPgSchema();
    $this->migrateAutSchema();

    expect(autColumn('aut_roles', 'id'))->toMatchArray(['data_type' => 'uuid', 'is_nullable' => 'NO'])
        ->and(autColumn('aut_roles', 'slug'))->toMatchArray(['data_type' => 'character varying', 'is_nullable' => 'NO'])
        ->and(autColumn('aut_roles', 'is_system'))->toMatchArray(['data_type' => 'boolean', 'is_nullable' => 'NO'])
        ->and(autColumn('aut_roles', 'assignable_to'))->toMatchArray(['data_type' => 'jsonb', 'is_nullable' => 'NO'])
        ->and(autColumn('aut_roles', 'deleted_at'))->toMatchArray(['data_type' => 'timestamp with time zone', 'is_nullable' => 'YES']);

    $indexDef = DB::selectOne("select indexdef from pg_indexes where indexname = 'aut_roles_slug_uq'")?->indexdef;
    expect($indexDef)->toContain('WHERE (deleted_at IS NULL)');

    // COMMENT ON COLUMN aut_roles.is_system (migration §1) — table itself carries no COMMENT ON TABLE.
    $columnComment = DB::selectOne("select col_description('aut_roles'::regclass, (select attnum from pg_attribute where attrelid = 'aut_roles'::regclass and attname = 'is_system')) as comment")?->comment;
    expect($columnComment)->not->toBeNull();
});

it('stores native direct grants with expiry and revocation fields', function (): void {
    $this->resetPgSchema();
    $this->migrateAutSchema();

    expect(autColumn('aut_direct_grants', 'principal_type'))->toMatchArray(['data_type' => 'character varying', 'is_nullable' => 'NO'])
        ->and(autColumn('aut_direct_grants', 'principal_id'))->toMatchArray(['data_type' => 'uuid', 'is_nullable' => 'NO'])
        ->and(autColumn('aut_direct_grants', 'permission_key'))->toMatchArray(['data_type' => 'character varying', 'is_nullable' => 'NO'])
        ->and(autColumn('aut_direct_grants', 'expires_at'))->toMatchArray(['data_type' => 'timestamp with time zone', 'is_nullable' => 'YES'])
        ->and(autColumn('aut_direct_grants', 'revoked_at'))->toMatchArray(['data_type' => 'timestamp with time zone', 'is_nullable' => 'YES']);
});

it('matches DB_SCHEMA §3.5 aut_role_matrix column-by-column', function (): void {
    $this->resetPgSchema();
    $this->migrateAutSchema();

    expect(autColumn('aut_role_matrix', 'entity_key'))->toMatchArray(['data_type' => 'character varying', 'is_nullable' => 'NO'])
        ->and(autColumn('aut_role_matrix', 'action'))->toMatchArray(['data_type' => 'character varying', 'is_nullable' => 'NO'])
        ->and(autColumn('aut_role_matrix', 'scope'))->toMatchArray(['data_type' => 'character varying', 'is_nullable' => 'NO'])
        ->and(autColumn('aut_role_matrix', 'context_type'))->toMatchArray(['data_type' => 'character varying', 'is_nullable' => 'YES'])
        ->and(autColumn('aut_role_matrix', 'context_id'))->toMatchArray(['data_type' => 'uuid', 'is_nullable' => 'YES']);

    expect(autConstraintDef('aut_role_matrix_action_ck'))->toContain('^[a-z][a-z0-9_-]*$');

    $scopeCheck = autConstraintDef('aut_role_matrix_scope_ck');
    foreach (['none', 'own', 'dept', 'dept_tree', 'workspace', 'account'] as $scope) {
        expect($scopeCheck)->toContain("'{$scope}'");
    }

    expect(autConstraintDef('aut_role_matrix_context_ck'))
        ->toContain('context_type IS NULL')
        ->toContain('context_id IS NULL');

    /** @var bool $nullsNotDistinct */
    $nullsNotDistinct = DB::selectOne("select i.indnullsnotdistinct from pg_constraint c join pg_index i on i.indexrelid = c.conindid where c.conname = 'aut_role_matrix_uq'")?->indnullsnotdistinct;
    expect($nullsNotDistinct)->toBeTrue();

    $comment = DB::selectOne("select obj_description('aut_role_matrix'::regclass, 'pg_class') as comment")?->comment;
    expect($comment)->not->toBeNull();
});

it('matches DB_SCHEMA §3.5 aut_role_assignments column-by-column', function (): void {
    $this->resetPgSchema();
    $this->migrateAutSchema();

    expect(autColumn('aut_role_assignments', 'principal_type'))->toMatchArray(['data_type' => 'character varying', 'is_nullable' => 'NO'])
        ->and(autColumn('aut_role_assignments', 'principal_id'))->toMatchArray(['data_type' => 'uuid', 'is_nullable' => 'NO'])
        ->and(autColumn('aut_role_assignments', 'workspace_id'))->toMatchArray(['data_type' => 'uuid', 'is_nullable' => 'YES'])
        ->and(autColumn('aut_role_assignments', 'granted_by'))->toMatchArray(['data_type' => 'uuid', 'is_nullable' => 'YES'])
        ->and(autColumn('aut_role_assignments', 'expires_at'))->toMatchArray(['data_type' => 'timestamp with time zone', 'is_nullable' => 'YES']);

    expect(autConstraintDef('aut_role_assignments_principal_type_ck'))->toContain("'user'")
        ->and(autConstraintDef('aut_role_assignments_principal_type_ck'))->toContain("'agent'")
        ->and(autConstraintDef('aut_role_assignments_principal_type_ck'))->toContain("'api_key'");

    /** @var bool $nullsNotDistinct */
    $nullsNotDistinct = DB::selectOne("select i.indnullsnotdistinct from pg_constraint c join pg_index i on i.indexrelid = c.conindid where c.conname = 'aut_role_assignments_uq'")?->indnullsnotdistinct;
    expect($nullsNotDistinct)->toBeTrue();

    $comment = DB::selectOne("select obj_description('aut_role_assignments'::regclass, 'pg_class') as comment")?->comment;
    expect($comment)->not->toBeNull();
});

// cross-package FK live only in the cloud migration.
it('adds both cross-package FKs only via the tenant-cloud migration', function (): void {
    $this->resetPgSchema();
    $this->migrateCloudFks();

    $workspaceFk = DB::selectOne("select confrelid::regclass::text as target from pg_constraint where conname = 'aut_role_assignments_workspace_fk'");
    $grantedByFk = DB::selectOne("select confrelid::regclass::text as target from pg_constraint where conname = 'aut_role_assignments_granted_by_fk'");

    expect($workspaceFk?->target)->toBe('wsp_workspaces')
        ->and($grantedByFk?->target)->toBe('users');
});
