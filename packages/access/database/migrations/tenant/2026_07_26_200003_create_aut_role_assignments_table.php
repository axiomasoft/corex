<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tenant-DB migration (B-11 §2.3, D14/D16) — назначение роли принципалу
// (user/agent/api_key, D101). Cross-package FK (workspace_id → wsp_workspaces,
// granted_by → users) — отдельной cloud-миграцией (D102): эти таблицы
// принадлежат corex/tenancy, отсутствующему в boxed-профиле (AC-28). См.
// DB_SCHEMA.md §3.5 aut_role_assignments.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aut_role_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('role_id');
            $table->string('principal_type', 16);
            $table->uuid('principal_id');
            $table->uuid('workspace_id')->nullable();
            $table->uuid('granted_by')->nullable();
            $table->timestampTz('expires_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->foreign('role_id')->references('id')->on('aut_roles')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE aut_role_assignments ADD CONSTRAINT aut_role_assignments_principal_type_ck CHECK (principal_type IN ('user','agent','api_key'))");
        DB::statement('ALTER TABLE aut_role_assignments ADD CONSTRAINT aut_role_assignments_uq UNIQUE NULLS NOT DISTINCT (role_id, principal_type, principal_id, workspace_id)');

        DB::statement('CREATE INDEX aut_assignments_principal_ix ON aut_role_assignments (principal_type, principal_id)');

        DB::statement("COMMENT ON TABLE aut_role_assignments IS 'twenty roleTarget: агент и api_key — полноправные principal. Права агента при запуске от пользователя = intersection(права инициатора, права роли агента) — enforcement в corex/ai (B-13); здесь только модель'");
    }

    public function down(): void
    {
        Schema::dropIfExists('aut_role_assignments');
    }
};
