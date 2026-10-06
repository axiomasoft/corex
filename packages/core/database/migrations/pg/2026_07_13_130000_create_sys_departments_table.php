<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Database\Laravel\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Executed from the package schema set on its declared connection.
return new class extends Migration
{
    public function up(): void
    {
        // Trusted extension since PG13 — role with CREATE on the database
        // suffices, no superuser required (RAG:✅ 2026-07-12 findings §4).
        DB::statement('CREATE EXTENSION IF NOT EXISTS ltree');

        $table = app(CoreOptions::class)->departmentsTable;

        Schema::create($table, function (Blueprint $table): void {
            IdColumns::primary($table);
            IdColumns::reference($table, 'workspace_id');
            IdColumns::reference($table, 'parent_id')->nullable();
            $table->string('name', 255);
            IdColumns::reference($table, 'head_user_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestampTz('deleted_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });

        // Self-referencing FK added AFTER the blueprint's own `primary`
        // command has run (Schema::create() defers the fluent ->primary()
        // to after any inline ->constrained(), which would try to add this
        // FK before sys_departments.id has a PK to reference).
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_dept_parent_fk FOREIGN KEY (parent_id) REFERENCES {$table} (id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE {$table} ADD COLUMN path ltree NOT NULL");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_dept_path_uq UNIQUE (path)");
        DB::statement("CREATE INDEX sys_dept_path_gist ON {$table} USING GIST (path)");
        DB::statement("CREATE INDEX sys_dept_wsp_ix ON {$table} (workspace_id) WHERE deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists(app(CoreOptions::class)->departmentsTable);
    }
};
