<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Complete the cycle after users and workspaces exist.
        DB::statement('ALTER TABLE sys_department_user ADD CONSTRAINT sys_department_user_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sys_department_user DROP CONSTRAINT sys_department_user_user_fk');
    }
};
