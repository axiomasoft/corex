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
        $this->create('crm_contact_company', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'crm_contact_company_pk');
            $table->uuid('contact_id');
            $table->uuid('company_id');
            $table->string('label', 64)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->addColumn('timestampTz', 'created_at', ['precision' => null])->useCurrent();
            $table->addColumn('timestampTz', 'updated_at', ['precision' => null])->useCurrent();

            $table->foreign(['company_id'], 'crm_contact_company_company_id_fk')->references(['id'])->on('crm_companies')->onDelete('cascade')->onUpdate('no action');
            $table->foreign(['contact_id'], 'crm_contact_company_contact_id_fk')->references(['id'])->on('crm_contacts')->onDelete('cascade')->onUpdate('no action');
            $table->unique(['contact_id', 'company_id'], 'crm_contact_company_contact_id_company_id_uq');
        });

        DB::statement('CREATE UNIQUE INDEX crm_contact_company_primary_uq ON crm_contact_company (contact_id) WHERE is_primary');
        DB::statement('CREATE INDEX "crm_contact_company_company_ix" on "crm_contact_company" ("company_id")');
    }

    public function down(): void
    {
        throw new RuntimeException('CRM parity rollback would discard data/provenance. Restore a verified backup in a separately authorized maintenance operation.');
    }
};
