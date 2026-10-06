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
        Schema::create('crm_pipelines', function (Blueprint $t): void {
            $t->uuid('id');
            $t->primary(['id'], 'crm_pipelines_pk');
            $t->uuid('workspace_id')->nullable();
            $t->string('entity_type', 64);
            $t->string('code', 64);
            $t->jsonb('name');
            $t->boolean('is_default')->default(false);
            $t->bigInteger('position')->default(Schema::getConnection()->getDriverName() === 'pgsql' ? DB::raw('0') : 0);
            $t->jsonb('settings')->default('{}');
            $t->addColumn('timestampTz', 'created_at', ['precision' => null])->useCurrent();
            $t->addColumn('timestampTz', 'updated_at', ['precision' => null])->useCurrent();
            $t->addColumn('timestampTz', 'deleted_at', ['precision' => null])->nullable();

            $t->foreign(['workspace_id'], 'crm_pipelines_workspace_id_fk')->references(['id'])->on('wsp_workspaces')->onDelete('cascade')->onUpdate('no action');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE crm_pipelines RENAME CONSTRAINT crm_pipelines_pkey TO crm_pipelines_pk');
        }

        $index = 'CREATE UNIQUE INDEX crm_pipelines_code_uq ON crm_pipelines';
        $index .= Schema::getConnection()->getDriverName() === 'pgsql' ? ' (entity_type, code, workspace_id) NULLS NOT DISTINCT' : " (entity_type, code, COALESCE(workspace_id, ''))";
        $index .= ' WHERE deleted_at IS NULL';
        DB::statement($index);
        $index = 'CREATE UNIQUE INDEX crm_pipelines_default_uq ON crm_pipelines';
        $index .= Schema::getConnection()->getDriverName() === 'pgsql' ? ' (entity_type, workspace_id) NULLS NOT DISTINCT' : " (entity_type, COALESCE(workspace_id, ''))";
        $index .= ' WHERE is_default AND deleted_at IS NULL';
        DB::statement($index);

    }

    public function down(): void
    {
        throw new RuntimeException('CRM parity rollback requires a verified provenance-preserving backup.');
    }
};
