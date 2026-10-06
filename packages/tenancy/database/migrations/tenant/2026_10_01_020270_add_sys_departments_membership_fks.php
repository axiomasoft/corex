<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Complete the cycle after users and workspaces exist.
        DB::statement('ALTER TABLE sys_departments ADD CONSTRAINT sys_departments_workspace_fk FOREIGN KEY (workspace_id) REFERENCES wsp_workspaces (id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE sys_departments ADD CONSTRAINT sys_departments_head_user_fk FOREIGN KEY (head_user_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sys_departments DROP CONSTRAINT sys_departments_head_user_fk');
        DB::statement('ALTER TABLE sys_departments DROP CONSTRAINT sys_departments_workspace_fk');
    }
};
