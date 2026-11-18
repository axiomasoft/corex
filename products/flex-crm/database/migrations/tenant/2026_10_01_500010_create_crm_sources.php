<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->create('crm_sources', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'crm_sources_pk');
            $table->uuid('workspace_id')->nullable();
            $table->string('code', 64);
            $table->jsonb('name');
            $table->boolean('is_active')->default(true);
            $table->bigInteger('position')->default(Schema::getConnection()->getDriverName() === 'pgsql' ? DB::raw('0') : 0);
            $table->addColumn('timestampTz', 'created_at', ['precision' => null])->useCurrent();
            $table->addColumn('timestampTz', 'updated_at', ['precision' => null])->useCurrent();

            $table->foreign(['workspace_id'], 'crm_sources_workspace_id_fk')->references(['id'])->on('wsp_workspaces')->onDelete('cascade')->onUpdate('no action');
            $table->unique([(Schema::getConnection()->getDriverName() === 'pgsql' ? 'workspace_id' : DB::raw("COALESCE(workspace_id, '')")), 'code'], 'crm_sources_workspace_id_code_uq')->nullsNotDistinct();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('CRM parity rollback would discard data/provenance. Restore a verified backup in a separately authorized maintenance operation.');
    }
};
