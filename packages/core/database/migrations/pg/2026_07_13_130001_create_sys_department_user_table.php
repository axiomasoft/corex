<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Executed from the package schema set on its declared connection.
return new class extends Migration
{
    public function up(): void
    {
        $table = 'sys_department_user';

        Schema::create($table, function (Blueprint $table): void {
            $table->foreignUuid('department_id')->constrained('sys_departments')->cascadeOnDelete();
            $table->uuid('user_id');
            $table->string('role', 16)->default('member');
            $table->boolean('is_primary')->default(false);
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->primary(['department_id', 'user_id']);
        });

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_du_role_ck CHECK (role IN ('member','head','deputy'))");
        DB::statement("CREATE UNIQUE INDEX sys_du_primary_uq ON {$table} (user_id) WHERE is_primary");
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_department_user');
    }
};
