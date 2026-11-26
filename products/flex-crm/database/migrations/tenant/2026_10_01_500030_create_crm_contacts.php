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
        $this->create('crm_contacts', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'crm_contacts_pk');
            $table->uuid('workspace_id')->nullable();
            $table->string('name_last', 120)->nullable();
            $table->string('name_first', 120)->nullable();
            $table->string('name_middle', 120)->nullable();
            $table->string('display_name', 255);
            $table->string('job_title', 190)->nullable();
            $table->jsonb('phones')->default('[]');
            $table->jsonb('emails')->default('[]');
            $table->date('birthday')->nullable();
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

            $table->foreign(['owner_id'], 'crm_contacts_owner_id_fk')->references(['id'])->on('users')->onDelete('set null')->onUpdate('no action');
            $table->foreign(['source_id'], 'crm_contacts_source_id_fk')->references(['id'])->on('crm_sources')->onDelete('set null')->onUpdate('no action');
            $table->foreign(['workspace_id'], 'crm_contacts_workspace_id_fk')->references(['id'])->on('wsp_workspaces')->onDelete('cascade')->onUpdate('no action');
            $table->index(['source_id'], 'crm_contacts_source_id_ix');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {

            DB::statement('CREATE INDEX crm_contacts_emails_ix ON crm_contacts USING gin (emails jsonb_path_ops)');

            DB::statement('CREATE INDEX crm_contacts_phones_ix ON crm_contacts USING gin (phones jsonb_path_ops)');
            DB::statement('CREATE INDEX crm_contacts_search_ix ON crm_contacts USING gin (search)');
        }

        DB::statement('CREATE INDEX crm_contacts_owner_ix ON crm_contacts (owner_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX crm_contacts_ws_ix ON crm_contacts (workspace_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        throw new RuntimeException('CRM parity rollback would discard data/provenance. Restore a verified backup in a separately authorized maintenance operation.');
    }
};
