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
        Schema::create('crm_deals', function (Blueprint $t): void {
            $t->uuid('id');
            $t->primary(['id'], 'crm_deals_pk');
            $t->uuid('workspace_id')->nullable();
            $t->string('title', 255);
            $t->string('status', 8)->default('open');
            $t->uuid('pipeline_id');
            $t->uuid('stage_id');
            $t->bigInteger('amount')->nullable();
            $t->char('currency', 3)->default('RUB');
            $t->date('close_date')->nullable();
            $t->addColumn('timestampTz', 'won_at', ['precision' => null])->nullable();
            $t->addColumn('timestampTz', 'lost_at', ['precision' => null])->nullable();
            $t->uuid('lost_reason_id')->nullable();
            $t->uuid('company_id')->nullable();
            $t->uuid('source_id')->nullable();
            $t->jsonb('utm')->nullable();
            $t->uuid('owner_id')->nullable();
            $t->jsonb('created_by');
            $t->uuid('primary_quote_doc_id')->nullable();
            $t->string('forecast_category', 16)->nullable();
            $t->boolean('forecast_category_is_manual')->default(false);
            $t->addColumn('timestampTz', 'stage_entered_at', ['precision' => null])->useCurrent();
            $t->decimal('position', 20, 10)->default(Schema::getConnection()->getDriverName() === 'pgsql' ? DB::raw('0') : 0);

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $t->addColumn('tsvector', 'search')->nullable();
            } else {
                $t->text('search')->nullable();
            }
            $t->addColumn('timestampTz', 'created_at', ['precision' => null])->useCurrent();
            $t->addColumn('timestampTz', 'updated_at', ['precision' => null])->useCurrent();
            $t->addColumn('timestampTz', 'deleted_at', ['precision' => null])->nullable();

            $t->foreign(['company_id'], 'crm_deals_company_id_fk')->references(['id'])->on('crm_companies')->onDelete('set null')->onUpdate('no action');
            $t->foreign(['lost_reason_id'], 'crm_deals_lost_reason_id_fk')->references(['id'])->on('crm_lost_reasons')->onDelete('set null')->onUpdate('no action');
            $t->foreign(['owner_id'], 'crm_deals_owner_id_fk')->references(['id'])->on('users')->onDelete('set null')->onUpdate('no action');
            $t->foreign(['pipeline_id'], 'crm_deals_pipeline_id_fk')->references(['id'])->on('crm_pipelines')->onDelete('restrict')->onUpdate('no action');
            $t->foreign(['source_id'], 'crm_deals_source_id_fk')->references(['id'])->on('crm_sources')->onDelete('set null')->onUpdate('no action');
            $t->foreign(['stage_id'], 'crm_deals_stage_id_fk')->references(['id'])->on('crm_stages')->onDelete('restrict')->onUpdate('no action');
            $t->foreign(['workspace_id'], 'crm_deals_workspace_id_fk')->references(['id'])->on('wsp_workspaces')->onDelete('cascade')->onUpdate('no action');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE crm_deals RENAME CONSTRAINT crm_deals_pkey TO crm_deals_pk');
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {

            DB::statement('ALTER TABLE crm_deals ADD CONSTRAINT crm_deals_parity_0_ck CHECK (status IN (\'open\',\'won\',\'lost\'))');
            DB::statement('ALTER TABLE crm_deals ADD CONSTRAINT crm_deals_parity_1_ck CHECK (status <> \'won\' OR won_at IS NOT NULL)');
            DB::statement('ALTER TABLE crm_deals ADD CONSTRAINT crm_deals_parity_2_ck CHECK (status <> \'lost\' OR lost_at IS NOT NULL)');
            DB::statement('ALTER TABLE crm_deals ADD CONSTRAINT crm_deals_parity_3_ck CHECK (forecast_category IN (\'pipeline\',\'best_case\',\'commit\',\'omitted\'))');

            DB::statement('CREATE INDEX crm_deals_search_ix ON crm_deals USING gin (search)');

        }

        DB::statement('CREATE INDEX crm_deals_close_ix ON crm_deals (close_date) WHERE status = \'open\' AND deleted_at IS NULL');
        DB::statement('CREATE INDEX crm_deals_ws_ix ON crm_deals (workspace_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX crm_deals_company_ix ON crm_deals (company_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX crm_deals_owner_ix ON crm_deals (owner_id, status) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX crm_deals_kanban_ix ON crm_deals (pipeline_id, stage_id, position) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX crm_deals_forecast_ix ON crm_deals (workspace_id, owner_id, pipeline_id, close_date, forecast_category) WHERE status = \'open\' AND close_date IS NOT NULL AND deleted_at IS NULL');

    }

    public function down(): void
    {
        throw new RuntimeException('CRM parity rollback requires a verified provenance-preserving backup.');
    }
};
