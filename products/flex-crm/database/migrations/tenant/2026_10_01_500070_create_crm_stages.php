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
        Schema::create('crm_stages', function (Blueprint $t): void {
            $t->uuid('id');
            $t->primary(['id'], 'crm_stages_pk');
            $t->uuid('pipeline_id');
            $t->string('code', 64);
            $t->jsonb('name');
            $t->string('color', 16)->nullable();
            $t->bigInteger('position')->default(Schema::getConnection()->getDriverName() === 'pgsql' ? DB::raw('0') : 0);
            $t->string('semantic', 8)->default('open');
            $t->smallInteger('probability')->default(Schema::getConnection()->getDriverName() === 'pgsql' ? DB::raw('0') : 0);
            $t->jsonb('required_fields')->default('[]');
            $t->jsonb('settings')->default('{}');
            $t->addColumn('timestampTz', 'created_at', ['precision' => null])->useCurrent();
            $t->addColumn('timestampTz', 'updated_at', ['precision' => null])->useCurrent();
            $t->addColumn('timestampTz', 'deleted_at', ['precision' => null])->nullable();

            $t->foreign(['pipeline_id'], 'crm_stages_pipeline_id_fk')->references(['id'])->on('crm_pipelines')->onDelete('cascade')->onUpdate('no action');
            $t->unique(['pipeline_id', 'code'], 'crm_stages_pipeline_id_code_uq');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE crm_stages RENAME CONSTRAINT crm_stages_pkey TO crm_stages_pk');
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE crm_stages ADD CONSTRAINT crm_stages_parity_0_ck CHECK (semantic IN (\'open\',\'won\',\'lost\'))');
            DB::statement('ALTER TABLE crm_stages ADD CONSTRAINT crm_stages_parity_1_ck CHECK (probability BETWEEN 0 AND 100)');

        }

        DB::statement('CREATE INDEX crm_stages_pipeline_ix ON crm_stages (pipeline_id, position) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        throw new RuntimeException('CRM parity rollback requires a verified provenance-preserving backup.');
    }
};
