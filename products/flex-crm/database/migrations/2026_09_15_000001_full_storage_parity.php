<?php

declare(strict_types=1);

use CoreX\Database\Laravel\IdColumns;
use Flex\Crm\Activity\Models\Activity;
use Flex\Crm\Deal\Models\Deal;
use Flex\Crm\Deal\Models\Pipeline;
use Flex\Crm\Deal\Models\Stage;
use Flex\Crm\Lead\Models\Lead;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [(new Activity)->getTable(), (new Lead)->getTable(), (new Deal)->getTable(), (new Stage)->getTable(), (new Pipeline)->getTable()];
        // All checks precede all DDL. No enum/actor/metadata conversion is inferred.
        foreach ([...$tables, 'crm_sources', 'crm_companies', 'crm_contacts', 'crm_contact_company', 'crm_lost_reasons', 'crm_activity_targets', 'crm_deal_contacts'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('CRM_PARITY_UPGRADE_REQUIRES_EXPORT: populated affected CRM storage requires provenance-preserving export. No CRM tables were changed.');
            }
        }
        foreach (['users' => ['id', 'is_active', 'deleted_at'], 'wsp_workspaces' => ['id', 'deleted_at'], 'wsp_workspace_members' => ['workspace_id', 'user_id', 'status'], 'crm_sources' => ['id'], 'crm_contacts' => ['id'], 'crm_companies' => ['id']] as $table => $columns) {
            if (! Schema::hasTable($table) || ! Schema::hasColumns($table, $columns)) {
                throw new RuntimeException("CRM prerequisite [{$table}] is missing or incompatible.");
            }
        }
        foreach (['users', 'wsp_workspaces', 'crm_sources', 'crm_contacts', 'crm_companies', ...$tables] as $table) {
            if (! Schema::hasTable($table) || Schema::getColumnType($table, 'id') !== Schema::getColumnType((new Deal)->getTable(), 'id')) {
                throw new RuntimeException("CRM prerequisite [{$table}.id] has an incompatible ID profile.");
            }
        }

        if (DB::getDriverName() === 'pgsql' && (int) DB::selectOne('SHOW server_version_num')->server_version_num < 150000) {
            throw new RuntimeException('CRM requires PostgreSQL 15+.');
        }
        DB::transaction(function () use ($tables): void {
            // Protect the emptiness proof against writers on existing tables.
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('LOCK TABLE '.implode(', ', [...$tables, 'crm_sources', 'crm_companies', 'crm_contacts', 'crm_contact_company']).' IN ACCESS EXCLUSIVE MODE');
                foreach ([...$tables, 'crm_sources', 'crm_companies', 'crm_contacts', 'crm_contact_company'] as $table) {
                    if (DB::table($table)->exists()) {
                        throw new RuntimeException('CRM_PARITY_UPGRADE_REQUIRES_EXPORT: storage became populated; no CRM tables were changed.');
                    }
                }
            }
            foreach ($tables as $table) {
                Schema::drop($table);
            }
            Schema::create('crm_lost_reasons', function (Blueprint $t): void {
                IdColumns::primary($t);
                $this->workspace($t);
                $t->string('code', 64);
                $t->jsonb('name');
                $t->string('entity_type', 16)->default('both');
                $t->boolean('is_active')->default(true);
                $t->integer('position')->default(0);
                $this->timestamps($t);
            });

            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE crm_lost_reasons ADD CONSTRAINT crm_lr_entity_ck CHECK (entity_type IN ('lead','deal','both'))");
            }
            $this->scopedUnique('crm_lost_reasons', 'crm_lr_code_uq', ['workspace_id', 'code']);
            Schema::create((new Pipeline)->getTable(), function (Blueprint $t): void {
                IdColumns::primary($t);
                $this->workspace($t);
                $t->string('entity_type', 64);
                $t->string('code', 64);
                $t->jsonb('name');
                $t->boolean('is_default')->default(false);
                $t->integer('position')->default(0);
                $t->jsonb('settings')->default('{}');
                $this->timestamps($t, soft: true);
            });
            $this->scopedUnique('crm_pipelines', 'crm_pipelines_code_uq', ['entity_type', 'code', 'workspace_id'], 'deleted_at IS NULL');
            $this->scopedUnique('crm_pipelines', 'crm_pipelines_default_uq', ['entity_type', 'workspace_id'], 'is_default AND deleted_at IS NULL');
            Schema::create((new Stage)->getTable(), function (Blueprint $t): void {
                IdColumns::primary($t);
                IdColumns::foreign($t, 'pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
                $t->string('code', 64);
                $t->jsonb('name');
                $t->string('color', 16)->nullable();
                $t->integer('position')->default(0);
                $t->string('semantic', 8)->default('open');
                $t->smallInteger('probability')->default(0);
                $t->jsonb('required_fields')->default('[]');
                $t->jsonb('settings')->default('{}');
                $this->timestamps($t, soft: true);
                $t->unique(['pipeline_id', 'code']);
            });
            Schema::create((new Lead)->getTable(), function (Blueprint $t): void {
                IdColumns::primary($t);
                $this->workspace($t);
                $t->string('title', 255);
                $t->string('status', 16)->default('open');
                IdColumns::foreign($t, 'pipeline_id')->nullable()->constrained('crm_pipelines')->restrictOnDelete();
                IdColumns::foreign($t, 'stage_id')->nullable()->constrained('crm_stages')->restrictOnDelete();
                $t->string('contact_name', 255)->nullable();
                $t->string('phone', 64)->nullable();
                $t->string('email', 255)->nullable();
                $t->string('company_name', 255)->nullable();
                $this->amount($t);
                $this->source($t);
                $t->jsonb('utm')->nullable();
                $this->lostReason($t);
                $this->provenance($t);
                $t->timestampTz('converted_at', null)->nullable();
                IdColumns::foreign($t, 'converted_deal_id')->nullable();
                IdColumns::foreign($t, 'converted_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
                IdColumns::foreign($t, 'converted_company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
                $t->decimal('position', 20, 10)->default(0);
                $t->text('search')->nullable();
                $this->timestamps($t, soft: true);
            });
            Schema::create((new Deal)->getTable(), function (Blueprint $t): void {
                IdColumns::primary($t);
                $this->workspace($t);
                $t->string('title', 255);
                $t->string('status', 8)->default('open');
                IdColumns::foreign($t, 'pipeline_id')->constrained('crm_pipelines')->restrictOnDelete();
                IdColumns::foreign($t, 'stage_id')->constrained('crm_stages')->restrictOnDelete();
                $this->amount($t);
                $t->date('close_date')->nullable();
                $t->timestampTz('won_at', null)->nullable();
                $t->timestampTz('lost_at', null)->nullable();
                $this->lostReason($t);
                IdColumns::foreign($t, 'company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
                $this->source($t);
                $t->jsonb('utm')->nullable();
                $this->provenance($t);
                IdColumns::foreign($t, 'primary_quote_doc_id')->nullable();
                $t->string('forecast_category', 16)->nullable();
                $t->boolean('forecast_category_is_manual')->default(false);
                $t->timestampTz('stage_entered_at', null)->useCurrent();
                $t->decimal('position', 20, 10)->default(0);
                $t->text('search')->nullable();
                $this->timestamps($t, soft: true);
            });
            Schema::table((new Lead)->getTable(), function (Blueprint $t): void {
                $t->foreign('converted_deal_id')->references('id')->on('crm_deals')->nullOnDelete();
            });
            Schema::create('crm_deal_contacts', function (Blueprint $t): void {
                IdColumns::primary($t);
                IdColumns::foreign($t, 'deal_id')->constrained('crm_deals')->cascadeOnDelete();
                IdColumns::foreign($t, 'contact_id')->constrained('crm_contacts')->cascadeOnDelete();
                $t->string('label', 64)->nullable();
                $t->boolean('is_primary')->default(false);
                $this->timestamps($t);
                $t->unique(['deal_id', 'contact_id']);
                $t->index('contact_id', 'crm_dc_contact_ix');
            });
            DB::statement('CREATE UNIQUE INDEX crm_dc_primary_uq ON crm_deal_contacts (deal_id) WHERE is_primary');
            Schema::create((new Activity)->getTable(), function (Blueprint $t): void {
                IdColumns::primary($t);
                $this->workspace($t);
                $t->string('kind', 16);
                $t->string('status', 16)->default('planned');
                $t->string('subject', 255)->nullable();
                $t->text('body')->nullable();
                $t->string('direction', 3)->nullable();
                $t->string('phone', 64)->nullable();
                $t->integer('duration_sec')->nullable();
                $t->string('location', 255)->nullable();
                $t->jsonb('email_meta')->nullable();
                IdColumns::foreign($t, 'recording_file_id')->nullable();
                $t->text('transcript')->nullable();
                $t->timestampTz('starts_at', null)->nullable();
                $t->timestampTz('ends_at', null)->nullable();
                $this->provenance($t);
                $this->timestamps($t, soft: true);
            });
            Schema::create('crm_activity_targets', function (Blueprint $t): void {
                IdColumns::foreign($t, 'activity_id')->constrained('crm_activities')->cascadeOnDelete();
                $t->string('entity_type', 64);
                IdColumns::foreign($t, 'entity_id');
                $t->timestampTz('created_at', null)->useCurrent();
                $t->primary(['activity_id', 'entity_type', 'entity_id']);
                $t->index(['entity_type', 'entity_id'], 'crm_at_entity_ix');
            });
            foreach (['crm_stages' => ["semantic IN ('open','won','lost')", 'probability BETWEEN 0 AND 100'], 'crm_leads' => ["status IN ('open','converted','junk')", "status <> 'converted' OR converted_at IS NOT NULL"], 'crm_deals' => ["status IN ('open','won','lost')", "status <> 'won' OR won_at IS NOT NULL", "status <> 'lost' OR lost_at IS NOT NULL", "forecast_category IN ('pipeline','best_case','commit','omitted')"], 'crm_activities' => ["kind IN ('call','email','meeting')", "status IN ('planned','done','canceled','missed')", "direction IN ('in','out')", "kind = 'call' OR direction IS NULL OR kind = 'email'"]] as $table => $checks) {
                if (DB::getDriverName() === 'pgsql') {
                    foreach ($checks as $i => $check) {
                        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_parity_{$i}_ck CHECK ({$check})");
                    }
                }
            }
            foreach (['crm_stages_pipeline_ix' => ['crm_stages', 'pipeline_id, position', 'deleted_at IS NULL'], 'crm_leads_kanban_ix' => ['crm_leads', 'pipeline_id, stage_id, position', 'deleted_at IS NULL'], 'crm_leads_owner_ix' => ['crm_leads', 'owner_id, status', 'deleted_at IS NULL'], 'crm_deals_kanban_ix' => ['crm_deals', 'pipeline_id, stage_id, position', 'deleted_at IS NULL'], 'crm_deals_owner_ix' => ['crm_deals', 'owner_id, status', 'deleted_at IS NULL'], 'crm_deals_company_ix' => ['crm_deals', 'company_id', 'deleted_at IS NULL'], 'crm_deals_ws_ix' => ['crm_deals', 'workspace_id', 'deleted_at IS NULL'], 'crm_deals_close_ix' => ['crm_deals', 'close_date', "status = 'open' AND deleted_at IS NULL"], 'crm_deals_forecast_ix' => ['crm_deals', 'workspace_id, owner_id, pipeline_id, close_date, forecast_category', "status = 'open' AND close_date IS NOT NULL AND deleted_at IS NULL"], 'crm_activities_planned_ix' => ['crm_activities', 'owner_id, starts_at', "status = 'planned' AND deleted_at IS NULL"], 'crm_activities_kind_ix' => ['crm_activities', 'kind, status', 'deleted_at IS NULL']] as $name => [$table, $columns, $where]) {
                DB::statement("CREATE INDEX {$name} ON {$table} ({$columns}) WHERE {$where}");
            }

            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE crm_stages ALTER COLUMN probability SET DEFAULT 0');
                foreach (['crm_leads', 'crm_deals'] as $table) {
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN position SET DEFAULT 0");
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN search TYPE tsvector USING search::tsvector");
                    DB::statement("CREATE INDEX {$table}_search_ix ON {$table} USING GIN (search)");
                }
                // Historical company/contact parity used timestamp(0); the approved
                // precision is unrestricted. All tables were proved empty above.
                foreach (['crm_sources', 'crm_companies', 'crm_contacts', 'crm_contact_company', 'crm_lost_reasons', 'crm_pipelines', 'crm_stages', 'crm_leads', 'crm_deals', 'crm_deal_contacts', 'crm_activities', 'crm_activity_targets'] as $table) {
                    foreach (Schema::getColumns($table) as $column) {
                        if ($column['type_name'] === 'timestamptz') {
                            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column['name']} TYPE timestamptz");
                        }
                    }
                }
            }
        });
    }

    /** @param list<string> $columns */
    private function scopedUnique(string $table, string $name, array $columns, ?string $where = null): void
    {
        if (DB::getDriverName() === 'pgsql' && $where === null) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} UNIQUE NULLS NOT DISTINCT (".implode(', ', $columns).')');

            return;
        }
        $columns = array_map(static fn (string $c): string => DB::getDriverName() !== 'pgsql' && $c === 'workspace_id' ? "COALESCE(workspace_id, '')" : $c, $columns);
        $nulls = DB::getDriverName() === 'pgsql' ? ' NULLS NOT DISTINCT' : '';
        DB::statement("CREATE UNIQUE INDEX {$name} ON {$table} (".implode(', ', $columns)."){$nulls}".($where === null ? '' : " WHERE {$where}"));
    }

    private function workspace(Blueprint $t): void
    {
        IdColumns::foreign($t, 'workspace_id')->nullable()->constrained('wsp_workspaces')->cascadeOnDelete();
    }

    private function source(Blueprint $t): void
    {
        IdColumns::foreign($t, 'source_id')->nullable()->constrained('crm_sources')->nullOnDelete();
    }

    private function lostReason(Blueprint $t): void
    {
        IdColumns::foreign($t, 'lost_reason_id')->nullable()->constrained('crm_lost_reasons')->nullOnDelete();
    }

    private function provenance(Blueprint $t): void
    {
        IdColumns::foreign($t, 'owner_id')->nullable()->constrained('users')->nullOnDelete();
        $t->jsonb('created_by');
    }

    private function amount(Blueprint $t): void
    {
        $t->bigInteger('amount')->nullable();
        $t->char('currency', 3)->default('RUB');
    }

    private function timestamps(Blueprint $t, bool $soft = false): void
    {
        $t->timestampTz('created_at', null)->useCurrent();
        $t->timestampTz('updated_at', null)->useCurrent();

        if ($soft) {
            $t->timestampTz('deleted_at', null)->nullable();
        }
    }

    public function down(): void
    {
        throw new RuntimeException('CRM parity rollback requires a verified provenance-preserving backup.');
    }
};
