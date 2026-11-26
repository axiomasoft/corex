<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $table = 'sys_department_user';

        $this->create($table, function (Blueprint $table): void {
            $table->uuid('department_id');
            $table->foreign(['department_id'], 'sys_department_user_department_fk')->references('id')->on('sys_departments')->cascadeOnDelete();
            $table->uuid('user_id');
            $table->string('role', 16)->default('member');
            $table->boolean('is_primary')->default(false);
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->primary(['department_id', 'user_id'], 'sys_department_user_pk');
        });

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_department_user_role_ck CHECK (role IN ('member','head','deputy'))");
        DB::statement("CREATE UNIQUE INDEX sys_department_user_primary_uq ON {$table} (user_id) WHERE is_primary");
    }

    public function down(): void
    {
        $this->drop('sys_department_user');
    }
};
