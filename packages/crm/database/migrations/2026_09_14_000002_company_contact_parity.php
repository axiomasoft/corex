<?php

declare(strict_types=1);

use CoreX\Support\Schema\IdColumns;
use Flex\Crm\Models\Company;
use Flex\Crm\Models\Contact;
use Flex\Crm\Models\ContactCompany;
use Flex\Crm\Models\Source;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The historical schema has no provenance. Do not invent actors, lose
        // custom fields or truncate tax IDs in an automatic upgrade.
        foreach ([(new Company)->getTable(), (new Contact)->getTable()] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('CRM_PARITY_UPGRADE_REQUIRES_EXPORT: populated legacy company/contact tables need an explicit provenance-preserving conversion. See packages/crm/UPGRADE.md. No CRM tables were changed.');
            }
        }
        foreach (['users' => ['id', 'is_active', 'deleted_at'], 'wsp_workspaces' => ['id', 'deleted_at'], 'wsp_workspace_members' => ['workspace_id', 'user_id', 'status']] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("CRM prerequisite [{$table}] is missing; initialize the account identity/workspace schema before enabling CRM.");
            }

            if (! Schema::hasColumns($table, $columns)) {
                throw new RuntimeException("CRM prerequisite [{$table}] does not implement the active-user/workspace-membership storage contract.");
            }
        }
        foreach (['users', 'wsp_workspaces'] as $table) {
            if (Schema::getColumnType($table, 'id') !== Schema::getColumnType((new Company)->getTable(), 'id')) {
                throw new RuntimeException("CRM prerequisite [{$table}.id] has an incompatible configured ID profile.");
            }
        }

        if (DB::getDriverName() === 'pgsql' && (int) DB::selectOne('SHOW server_version_num')->server_version_num < 150000) {
            throw new RuntimeException('CRM requires PostgreSQL 15+ for NULLS NOT DISTINCT.');
        }
        Schema::drop((new Contact)->getTable());
        Schema::drop((new Company)->getTable());
        Schema::create((new Source)->getTable(), function (Blueprint $table): void {
            IdColumns::primary($table);
            IdColumns::foreign($table, 'workspace_id')->nullable()->constrained('wsp_workspaces')->cascadeOnDelete();
            $table->string('code', 64);
            $table->jsonb('name');
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);
            $this->timestamps($table);
        });
        DB::statement(DB::getDriverName() === 'pgsql'
            ? 'ALTER TABLE crm_sources ADD CONSTRAINT crm_sources_workspace_id_code_unique UNIQUE NULLS NOT DISTINCT (workspace_id, code)'
            : "CREATE UNIQUE INDEX crm_sources_workspace_id_code_unique ON crm_sources (COALESCE(workspace_id, ''), code)");
        Schema::create((new Company)->getTable(), function (Blueprint $table): void {
            IdColumns::primary($table);
            IdColumns::foreign($table, 'workspace_id')->nullable()->constrained('wsp_workspaces')->cascadeOnDelete();
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
            $this->recordColumns($table);
        });
        Schema::create((new Contact)->getTable(), function (Blueprint $table): void {
            IdColumns::primary($table);
            IdColumns::foreign($table, 'workspace_id')->nullable()->constrained('wsp_workspaces')->cascadeOnDelete();
            $table->string('name_last', 120)->nullable();
            $table->string('name_first', 120)->nullable();
            $table->string('name_middle', 120)->nullable();
            $table->string('display_name', 255);
            $table->string('job_title', 190)->nullable();
            $table->jsonb('phones')->default('[]');
            $table->jsonb('emails')->default('[]');
            $table->date('birthday')->nullable();
            $this->recordColumns($table);
        });
        Schema::create((new ContactCompany)->getTable(), function (Blueprint $table): void {
            IdColumns::primary($table);
            IdColumns::foreign($table, 'contact_id')->constrained((new Contact)->getTable())->cascadeOnDelete();
            IdColumns::foreign($table, 'company_id')->constrained((new Company)->getTable())->cascadeOnDelete();
            $table->string('label', 64)->nullable();
            $table->boolean('is_primary')->default(false);
            $this->timestamps($table);
            $table->unique(['contact_id', 'company_id']);
            $table->index('company_id', 'crm_cc_company_ix');
        });
        DB::statement('CREATE UNIQUE INDEX crm_cc_primary_uq ON crm_contact_company (contact_id) WHERE is_primary');
        foreach (['crm_companies', 'crm_contacts'] as $table) {
            DB::statement("CREATE INDEX {$table}_owner_ix ON {$table} (owner_id) WHERE deleted_at IS NULL");
            DB::statement("CREATE INDEX {$table}_ws_ix ON {$table} (workspace_id) WHERE deleted_at IS NULL");

            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN search TYPE tsvector USING search::tsvector");
                DB::statement("CREATE INDEX {$table}_search_ix ON {$table} USING GIN (search)");
                foreach (['phones', 'emails'] as $field) {
                    DB::statement("CREATE INDEX {$table}_{$field}_ix ON {$table} USING GIN ({$field} jsonb_path_ops)");
                }
            }
        }
        DB::statement('CREATE INDEX crm_companies_inn_ix ON crm_companies (inn) WHERE inn IS NOT NULL AND deleted_at IS NULL');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON COLUMN crm_companies.inn IS 'Non-unique: duplicate tax identifiers are permitted CRM data.'");
        }
    }

    private function recordColumns(Blueprint $table): void
    {
        IdColumns::foreign($table, 'source_id')->nullable()->constrained((new Source)->getTable())->nullOnDelete();
        IdColumns::foreign($table, 'owner_id')->nullable()->constrained('users')->nullOnDelete();
        $table->jsonb('created_by');
        $table->text('search')->nullable();
        $this->timestamps($table);
        $table->timestampTz('deleted_at')->nullable();
    }

    private function timestamps(Blueprint $table): void
    {
        $table->timestampTz('created_at')->useCurrent();
        $table->timestampTz('updated_at')->useCurrent();
    }

    public function down(): void
    {
        throw new RuntimeException('CRM parity rollback would discard data/provenance. Restore a verified backup in a separately authorized maintenance operation.');
    }
};
