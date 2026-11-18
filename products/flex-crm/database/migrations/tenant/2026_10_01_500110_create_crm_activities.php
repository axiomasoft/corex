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
        $this->create('crm_activities', function (Blueprint $t): void {
            $t->uuid('id');
            $t->primary(['id'], 'crm_activities_pk');
            $t->uuid('workspace_id')->nullable();
            $t->string('kind', 16);
            $t->string('status', 16)->default('planned');
            $t->string('subject', 255)->nullable();
            $t->text('body')->nullable();
            $t->string('direction', 3)->nullable();
            $t->string('phone', 64)->nullable();
            $t->bigInteger('duration_sec')->nullable();
            $t->string('location', 255)->nullable();
            $t->jsonb('email_meta')->nullable();
            $t->uuid('recording_file_id')->nullable();
            $t->text('transcript')->nullable();
            $t->addColumn('timestampTz', 'starts_at', ['precision' => null])->nullable();
            $t->addColumn('timestampTz', 'ends_at', ['precision' => null])->nullable();
            $t->uuid('owner_id')->nullable();
            $t->jsonb('created_by');
            $t->addColumn('timestampTz', 'created_at', ['precision' => null])->useCurrent();
            $t->addColumn('timestampTz', 'updated_at', ['precision' => null])->useCurrent();
            $t->addColumn('timestampTz', 'deleted_at', ['precision' => null])->nullable();

            $t->foreign(['owner_id'], 'crm_activities_owner_id_fk')->references(['id'])->on('users')->onDelete('set null')->onUpdate('no action');
            $t->foreign(['workspace_id'], 'crm_activities_workspace_id_fk')->references(['id'])->on('wsp_workspaces')->onDelete('cascade')->onUpdate('no action');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {

            DB::statement('ALTER TABLE crm_activities ADD CONSTRAINT crm_activities_parity_0_ck CHECK (kind IN (\'call\',\'email\',\'meeting\'))');
            DB::statement('ALTER TABLE crm_activities ADD CONSTRAINT crm_activities_parity_1_ck CHECK (status IN (\'planned\',\'done\',\'canceled\',\'missed\'))');
            DB::statement('ALTER TABLE crm_activities ADD CONSTRAINT crm_activities_parity_2_ck CHECK (direction IN (\'in\',\'out\'))');
            DB::statement('ALTER TABLE crm_activities ADD CONSTRAINT crm_activities_parity_3_ck CHECK (kind = \'call\' OR direction IS NULL OR kind = \'email\')');
        }

        DB::statement('CREATE INDEX crm_activities_kind_ix ON crm_activities (kind, status) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX crm_activities_planned_ix ON crm_activities (owner_id, starts_at) WHERE status = \'planned\' AND deleted_at IS NULL');
    }

    public function down(): void
    {
        throw new RuntimeException('CRM parity rollback requires a verified provenance-preserving backup.');
    }
};
