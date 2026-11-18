<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Packman\Schema\Laravel\PackmanMigration;

// Executed from the package schema set on its declared connection.
return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

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
