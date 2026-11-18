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
        Schema::table('crm_leads', function (Blueprint $t): void {
            $t->foreign(['converted_deal_id'], 'crm_leads_converted_deal_id_fk')->references(['id'])->on('crm_deals')->onDelete('set null')->onUpdate('no action');
        });

        // SQLite rebuilds the table when adding this cyclic FK; create partial indexes afterwards.
        DB::statement('CREATE INDEX crm_leads_kanban_ix ON crm_leads (pipeline_id, stage_id, position) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX crm_leads_owner_ix ON crm_leads (owner_id, status) WHERE deleted_at IS NULL');

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX crm_leads_search_ix ON crm_leads USING gin (search)');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('CRM parity rollback requires a verified provenance-preserving backup.');
    }
};
