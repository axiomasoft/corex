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
        Schema::create('crm_deal_contacts', function (Blueprint $t): void {
            $t->uuid('id');
            $t->primary(['id'], 'crm_deal_contacts_pk');
            $t->uuid('deal_id');
            $t->uuid('contact_id');
            $t->string('label', 64)->nullable();
            $t->boolean('is_primary')->default(false);
            $t->addColumn('timestampTz', 'created_at', ['precision' => null])->useCurrent();
            $t->addColumn('timestampTz', 'updated_at', ['precision' => null])->useCurrent();

            $t->foreign(['contact_id'], 'crm_deal_contacts_contact_id_fk')->references(['id'])->on('crm_contacts')->onDelete('cascade')->onUpdate('no action');
            $t->foreign(['deal_id'], 'crm_deal_contacts_deal_id_fk')->references(['id'])->on('crm_deals')->onDelete('cascade')->onUpdate('no action');
            $t->unique(['deal_id', 'contact_id'], 'crm_deal_contacts_deal_id_contact_id_uq');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE crm_deal_contacts RENAME CONSTRAINT crm_deal_contacts_pkey TO crm_deal_contacts_pk');
        }

        DB::statement('CREATE UNIQUE INDEX crm_deal_contacts_primary_uq ON crm_deal_contacts (deal_id) WHERE is_primary');
        DB::statement('CREATE INDEX "crm_deal_contacts_contact_ix" on "crm_deal_contacts" ("contact_id")');
    }

    public function down(): void
    {
        throw new RuntimeException('CRM parity rollback requires a verified provenance-preserving backup.');
    }
};
