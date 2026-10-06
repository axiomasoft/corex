<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS ltree');

        $table = 'sys_departments';

        Schema::create($table, function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'sys_departments_pk');
            $table->uuid('workspace_id');
            $table->uuid('parent_id')->nullable();
            $table->string('name', 255);
            $table->uuid('head_user_id')->nullable();
            $table->bigInteger('sort_order')->default(0);
            $table->timestampTz('deleted_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });

        Schema::getConnection()->statement('ALTER TABLE sys_departments RENAME CONSTRAINT sys_departments_pkey TO sys_departments_pk');

        // Add the self-reference after the primary key exists.
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_departments_parent_fk FOREIGN KEY (parent_id) REFERENCES {$table} (id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE {$table} ADD COLUMN path ltree NOT NULL");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_departments_path_uq UNIQUE (path)");
        DB::statement("CREATE INDEX sys_departments_path_gist_ix ON {$table} USING GIST (path)");
        DB::statement("CREATE INDEX sys_departments_workspace_ix ON {$table} (workspace_id) WHERE deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_departments');
    }
};
