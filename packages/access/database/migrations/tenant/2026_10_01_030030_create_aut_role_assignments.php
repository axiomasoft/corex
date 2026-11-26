<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Packman\Schema\Laravel\PackmanMigration;

// Grants a role to a user, agent or API key. A separate cloud migration adds
// the foreign keys from workspace_id to wsp_workspaces and granted_by to users.
// The optional tenancy package owns those tables.
return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->create('aut_role_assignments', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'aut_role_assignments_pk');
            $table->uuid('role_id');
            $table->string('principal_type', 16);
            $table->uuid('principal_id');
            $table->uuid('workspace_id')->nullable();
            $table->uuid('granted_by')->nullable();
            $table->timestampTz('expires_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->foreign('role_id', 'aut_role_assignments_role_id_fk')->references('id')->on('aut_roles')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE aut_role_assignments ADD CONSTRAINT aut_role_assignments_principal_type_ck CHECK (principal_type IN ('user','agent','api_key'))");
        DB::statement('ALTER TABLE aut_role_assignments ADD CONSTRAINT aut_role_assignments_uq UNIQUE NULLS NOT DISTINCT (role_id, principal_type, principal_id, workspace_id)');

        DB::statement('CREATE INDEX aut_role_assignments_principal_ix ON aut_role_assignments (principal_type, principal_id)');
    }

    public function down(): void
    {
        $this->drop('aut_role_assignments');
    }
};
