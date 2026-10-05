<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tenant-DB authorization roles; bridge role links live in the bridge package.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aut_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 64);
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->uuid('cloned_from_id')->nullable();
            $table->jsonb('assignable_to')->default('["user"]');
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
            $table->timestampTz('deleted_at', precision: 6)->nullable();
        });

        // Self-referencing FK added after the blueprint's own inline
        // ->primary() has run (same ordering as wsp_workspaces.parent_id).
        DB::statement('ALTER TABLE aut_roles ADD CONSTRAINT aut_roles_cloned_from_fk FOREIGN KEY (cloned_from_id) REFERENCES aut_roles (id) ON DELETE SET NULL');

        DB::statement('CREATE UNIQUE INDEX aut_roles_slug_uq ON aut_roles (slug) WHERE deleted_at IS NULL');

        DB::statement("COMMENT ON COLUMN aut_roles.is_system IS 'Системная роль: матрица read-only в UI, обновляется релизами; кастомизация = клонировать и править клон (Б24-модель)'");
    }

    public function down(): void
    {
        Schema::dropIfExists('aut_roles');
    }
};
