<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Packman\Schema\Laravel\PackmanMigration;

// Tenant role matrix used by DatabaseRoleMatrixCompiler. Each row associates a
// role, entity and action with a scope. EntityRegistry supplies entity keys at
// runtime, so entity_key has no foreign key.
return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->create('aut_role_matrix', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'aut_role_matrix_pk');
            $table->uuid('role_id');
            $table->string('entity_key', 64);
            $table->string('action', 160);
            $table->string('scope', 16);
            $table->string('context_type', 32)->nullable();
            $table->uuid('context_id')->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->foreign('role_id', 'aut_role_matrix_role_id_fk')->references('id')->on('aut_roles')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE aut_role_matrix ADD CONSTRAINT aut_role_matrix_action_ck CHECK (action ~ '^[a-z][a-z0-9_-]*$' OR action ~ '^field[.][A-Za-z0-9][A-Za-z0-9._:-]{0,127}[.](read|write|search|filter|sort|aggregate|export)$')");
        DB::statement("ALTER TABLE aut_role_matrix ADD CONSTRAINT aut_role_matrix_scope_ck CHECK (scope IN ('none','own','dept','dept_tree','workspace','account'))");
        DB::statement('ALTER TABLE aut_role_matrix ADD CONSTRAINT aut_role_matrix_context_ck CHECK ((context_type IS NULL) = (context_id IS NULL))');
        DB::statement('ALTER TABLE aut_role_matrix ADD CONSTRAINT aut_role_matrix_uq UNIQUE NULLS NOT DISTINCT (role_id, entity_key, action, context_type, context_id)');

        DB::statement('CREATE INDEX aut_role_matrix_entity_ix ON aut_role_matrix (entity_key)');
    }

    public function down(): void
    {
        $this->drop('aut_role_matrix');
    }
};
