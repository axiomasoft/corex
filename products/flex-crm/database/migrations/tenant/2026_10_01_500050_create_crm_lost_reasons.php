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
        Schema::create('crm_lost_reasons', function (Blueprint $t): void {
            $t->uuid('id');
            $t->primary(['id'], 'crm_lost_reasons_pk');
            $t->uuid('workspace_id')->nullable();
            $t->string('code', 64);
            $t->jsonb('name');
            $t->string('entity_type', 16)->default('both');
            $t->boolean('is_active')->default(true);
            $t->bigInteger('position')->default(Schema::getConnection()->getDriverName() === 'pgsql' ? DB::raw('0') : 0);
            $t->addColumn('timestampTz', 'created_at', ['precision' => null])->useCurrent();
            $t->addColumn('timestampTz', 'updated_at', ['precision' => null])->useCurrent();

            $t->foreign(['workspace_id'], 'crm_lost_reasons_workspace_id_fk')->references(['id'])->on('wsp_workspaces')->onDelete('cascade')->onUpdate('no action');
            $t->unique([(Schema::getConnection()->getDriverName() === 'pgsql' ? 'workspace_id' : DB::raw("COALESCE(workspace_id, '')")), 'code'], 'crm_lost_reasons_code_uq')->nullsNotDistinct();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE crm_lost_reasons RENAME CONSTRAINT crm_lost_reasons_pkey TO crm_lost_reasons_pk');
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {

            DB::statement('ALTER TABLE crm_lost_reasons ADD CONSTRAINT crm_lost_reasons_entity_ck CHECK (entity_type IN (\'lead\',\'deal\',\'both\'))');
        }

    }

    public function down(): void
    {
        throw new RuntimeException('CRM parity rollback requires a verified provenance-preserving backup.');
    }
};
