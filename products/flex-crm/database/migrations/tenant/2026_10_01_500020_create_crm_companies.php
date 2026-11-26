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
        $this->create('crm_companies', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'crm_companies_pk');
            $table->uuid('workspace_id')->nullable();
            $table->string('name', 255);
            $table->string('legal_name', 255)->nullable();
            $table->string('domain', 190)->nullable();
            $table->string('inn', 12)->nullable();
            $table->string('kpp', 9)->nullable();
            $table->string('ogrn', 15)->nullable();
            $table->jsonb('phones')->default('[]');
            $table->jsonb('emails')->default('[]');
            $table->jsonb('address')->nullable();
            $table->jsonb('bank_details')->nullable();
            $table->uuid('source_id')->nullable();
            $table->uuid('owner_id')->nullable();
            $table->jsonb('created_by');

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->addColumn('tsvector', 'search')->nullable();
            } else {
                $table->text('search')->nullable();
            }
            $table->addColumn('timestampTz', 'created_at', ['precision' => null])->useCurrent();
            $table->addColumn('timestampTz', 'updated_at', ['precision' => null])->useCurrent();
            $table->addColumn('timestampTz', 'deleted_at', ['precision' => null])->nullable();

            $table->foreign(['owner_id'], 'crm_companies_owner_id_fk')->references(['id'])->on('users')->onDelete('set null')->onUpdate('no action');
            $table->foreign(['source_id'], 'crm_companies_source_id_fk')->references(['id'])->on('crm_sources')->onDelete('set null')->onUpdate('no action');
            $table->foreign(['workspace_id'], 'crm_companies_workspace_id_fk')->references(['id'])->on('wsp_workspaces')->onDelete('cascade')->onUpdate('no action');
            $table->index(['source_id'], 'crm_companies_source_id_ix');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {

            DB::statement('CREATE INDEX crm_companies_emails_ix ON crm_companies USING gin (emails jsonb_path_ops)');

            DB::statement('CREATE INDEX crm_companies_phones_ix ON crm_companies USING gin (phones jsonb_path_ops)');
            DB::statement('CREATE INDEX crm_companies_search_ix ON crm_companies USING gin (search)');

            DB::statement('COMMENT ON COLUMN crm_companies.inn IS \'Non-unique: duplicate tax identifiers are permitted CRM data.\'');
        }

        DB::statement('CREATE INDEX crm_companies_ws_ix ON crm_companies (workspace_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX crm_companies_owner_ix ON crm_companies (owner_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX crm_companies_inn_ix ON crm_companies (inn) WHERE inn IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        throw new RuntimeException('CRM parity rollback would discard data/provenance. Restore a verified backup in a separately authorized maintenance operation.');
    }
};
