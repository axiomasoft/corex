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
        $this->create('wsp_workspace_members', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'wsp_workspace_members_pk');
            $table->uuid('workspace_id');
            $table->uuid('user_id');
            $table->string('status', 16)->default('active');
            $table->boolean('is_default')->default(false);
            $table->timestampTz('joined_at', precision: 6)->useCurrent();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->foreign('workspace_id', 'wsp_workspace_members_workspace_id_fk')->references('id')->on('wsp_workspaces')->cascadeOnDelete();
            $table->foreign('user_id', 'wsp_workspace_members_user_id_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['workspace_id', 'user_id'], 'wsp_workspace_members_workspace_id_user_id_uq');
        });

        DB::statement("ALTER TABLE wsp_workspace_members ADD CONSTRAINT wsp_workspace_members_status_ck CHECK (status IN ('active','suspended'))");

        DB::statement('CREATE INDEX wsp_workspace_members_user_ix ON wsp_workspace_members (user_id)');
        DB::statement('CREATE UNIQUE INDEX wsp_workspace_members_default_uq ON wsp_workspace_members (user_id) WHERE is_default');

        DB::statement("COMMENT ON TABLE wsp_workspace_members IS 'Membership = fast admission flag for the resolver (403 without it); PERMISSIONS live only in axiomasoft/corex-access (roles) — role not stored in this pivot'");
    }

    public function down(): void
    {
        $this->drop('wsp_workspace_members');
    }
};
