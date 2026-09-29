<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Cross-package cloud-only FK (D102, C4/D14-precedent): aut_role_assignments
// → wsp_workspaces/users live in axiomasoft/corex-tenancy, absent in boxed installs
// (AC-28 — axiomasoft/corex-access must be fully functional without it). tenant/
// migrations above carry no FK/hasTable-guard on these two columns — a
// silent guard-skip in the cloud profile would be indistinguishable from
// success. Publish-tag corex-auth-migrations-tenant-cloud (cloud profile
// only; boxed publishes tenant/ alone).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE aut_role_assignments ADD CONSTRAINT aut_role_assignments_workspace_fk FOREIGN KEY (workspace_id) REFERENCES wsp_workspaces (id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE aut_role_assignments ADD CONSTRAINT aut_role_assignments_granted_by_fk FOREIGN KEY (granted_by) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE aut_role_assignments DROP CONSTRAINT aut_role_assignments_granted_by_fk');
        DB::statement('ALTER TABLE aut_role_assignments DROP CONSTRAINT aut_role_assignments_workspace_fk');
    }
};
