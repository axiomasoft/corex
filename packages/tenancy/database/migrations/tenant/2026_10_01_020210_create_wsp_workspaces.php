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
        DB::statement('CREATE EXTENSION IF NOT EXISTS ltree');

        $this->create('wsp_workspaces', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'wsp_workspaces_pk');
            $table->uuid('parent_id')->nullable();
            $table->string('type', 16)->default('company');
            $table->string('name', 255);
            $table->string('slug', 63);
            $table->boolean('is_default')->default(false);
            $table->jsonb('settings')->default('{}');
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
            $table->timestampTz('deleted_at', precision: 6)->nullable();
        });

        // Self-referencing FK added AFTER the blueprint's own inline
        // ->primary() has run (Schema::create() would otherwise try to add
        // this FK before wsp_workspaces.id has a PK to reference) — same
        // ordering as sys_departments' self-referencing parent_id (P1.10).
        DB::statement('ALTER TABLE wsp_workspaces ADD CONSTRAINT wsp_workspaces_parent_fk FOREIGN KEY (parent_id) REFERENCES wsp_workspaces (id) ON DELETE RESTRICT');

        DB::statement("ALTER TABLE wsp_workspaces ADD CONSTRAINT wsp_workspaces_type_ck CHECK (type IN ('company','branch','partner'))");
        DB::statement('ALTER TABLE wsp_workspaces ADD COLUMN path ltree NOT NULL');

        DB::statement('CREATE UNIQUE INDEX wsp_workspaces_slug_uq ON wsp_workspaces (slug) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX wsp_workspaces_default_uq ON wsp_workspaces ((true)) WHERE is_default AND deleted_at IS NULL');
        DB::statement('CREATE INDEX wsp_workspaces_path_gist_ix ON wsp_workspaces USING GIST (path)');

        DB::statement("COMMENT ON COLUMN wsp_workspaces.path IS 'parent_id — for FK/UI; path — for hierarchical predicates (path <@ :subtree). type=partner: membership does not grant parent visibility (B-11 §5.4)'");
    }

    public function down(): void
    {
        $this->drop('wsp_workspaces');
    }
};
