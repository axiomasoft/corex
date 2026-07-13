<?php

declare(strict_types=1);

use CoreX\Support\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PG-only (D12), depends on sys_departments (previous migration in this
// batch). user_id is a loose reference — the FK lands with corex/auth (P2,
// DB_SCHEMA C4). See DB_SCHEMA.md §3.2 sys_department_user; B-10 §2 DDL.
return new class extends Migration
{
    public function up(): void
    {
        $table = Config::departmentUserTable();

        Schema::create($table, function (Blueprint $table): void {
            $table->foreignUuid('department_id')->constrained(Config::departmentsTable())->cascadeOnDelete();
            $table->uuid('user_id');
            $table->string('role', 16)->default('member');
            $table->boolean('is_primary')->default(false);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->primary(['department_id', 'user_id']);
        });

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_du_role_ck CHECK (role IN ('member','head','deputy'))");
        DB::statement("CREATE UNIQUE INDEX sys_du_primary_uq ON {$table} (user_id) WHERE is_primary");
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::departmentUserTable());
    }
};
