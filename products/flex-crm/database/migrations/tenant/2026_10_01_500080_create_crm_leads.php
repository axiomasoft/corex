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
        $this->create('crm_leads', function (Blueprint $t): void {
            $t->uuid('id');
            $t->primary(['id'], 'crm_leads_pk');
            $t->uuid('workspace_id')->nullable();
            $t->string('title', 255);
            $t->string('status', 16)->default('open');
            $t->uuid('pipeline_id')->nullable();
            $t->uuid('stage_id')->nullable();
            $t->string('contact_name', 255)->nullable();
            $t->string('phone', 64)->nullable();
            $t->string('email', 255)->nullable();
            $t->string('company_name', 255)->nullable();
            $t->bigInteger('amount')->nullable();
            $t->char('currency', 3)->default('RUB');
            $t->uuid('source_id')->nullable();
            $t->jsonb('utm')->nullable();
            $t->uuid('lost_reason_id')->nullable();
            $t->uuid('owner_id')->nullable();
            $t->jsonb('created_by');
            $t->addColumn('timestampTz', 'converted_at', ['precision' => null])->nullable();
            $t->uuid('converted_deal_id')->nullable();
            $t->uuid('converted_contact_id')->nullable();
            $t->uuid('converted_company_id')->nullable();
            $t->decimal('position', 20, 10)->default(Schema::getConnection()->getDriverName() === 'pgsql' ? DB::raw('0') : 0);

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $t->addColumn('tsvector', 'search')->nullable();
            } else {
                $t->text('search')->nullable();
            }
            $t->addColumn('timestampTz', 'created_at', ['precision' => null])->useCurrent();
            $t->addColumn('timestampTz', 'updated_at', ['precision' => null])->useCurrent();
            $t->addColumn('timestampTz', 'deleted_at', ['precision' => null])->nullable();

            $t->foreign(['converted_company_id'], 'crm_leads_converted_company_id_fk')->references(['id'])->on('crm_companies')->onDelete('set null')->onUpdate('no action');
            $t->foreign(['converted_contact_id'], 'crm_leads_converted_contact_id_fk')->references(['id'])->on('crm_contacts')->onDelete('set null')->onUpdate('no action');
            $t->foreign(['lost_reason_id'], 'crm_leads_lost_reason_id_fk')->references(['id'])->on('crm_lost_reasons')->onDelete('set null')->onUpdate('no action');
            $t->foreign(['owner_id'], 'crm_leads_owner_id_fk')->references(['id'])->on('users')->onDelete('set null')->onUpdate('no action');
            $t->foreign(['pipeline_id'], 'crm_leads_pipeline_id_fk')->references(['id'])->on('crm_pipelines')->onDelete('restrict')->onUpdate('no action');
            $t->foreign(['source_id'], 'crm_leads_source_id_fk')->references(['id'])->on('crm_sources')->onDelete('set null')->onUpdate('no action');
            $t->foreign(['stage_id'], 'crm_leads_stage_id_fk')->references(['id'])->on('crm_stages')->onDelete('restrict')->onUpdate('no action');
            $t->foreign(['workspace_id'], 'crm_leads_workspace_id_fk')->references(['id'])->on('wsp_workspaces')->onDelete('cascade')->onUpdate('no action');
            $t->index(['converted_company_id'], 'crm_leads_converted_company_id_ix');
            $t->index(['converted_contact_id'], 'crm_leads_converted_contact_id_ix');
            $t->index(['converted_deal_id'], 'crm_leads_converted_deal_id_ix');
            $t->index(['lost_reason_id'], 'crm_leads_lost_reason_id_ix');
            $t->index(['source_id'], 'crm_leads_source_id_ix');
            $t->index(['stage_id'], 'crm_leads_stage_id_ix');
            $t->index(['workspace_id'], 'crm_leads_workspace_id_ix');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {

            DB::statement('ALTER TABLE crm_leads ADD CONSTRAINT crm_leads_parity_0_ck CHECK (status IN (\'open\',\'converted\',\'junk\'))');
            DB::statement('ALTER TABLE crm_leads ADD CONSTRAINT crm_leads_parity_1_ck CHECK (status <> \'converted\' OR converted_at IS NOT NULL)');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('CRM parity rollback requires a verified provenance-preserving backup.');
    }
};
