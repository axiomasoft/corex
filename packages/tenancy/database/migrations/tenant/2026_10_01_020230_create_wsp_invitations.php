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
        $this->create('wsp_invitations', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'wsp_invitations_pk');
            $table->string('email', 255);
            $table->uuid('workspace_id')->nullable();
            $table->uuid('role_id')->nullable();
            $table->uuid('invited_by');
            $table->string('token_hash', 64)->unique('wsp_invitations_token_hash_uq');
            $table->string('status', 16)->default('pending');
            $table->timestampTz('expires_at', precision: 6);
            $table->timestampTz('accepted_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->foreign('workspace_id', 'wsp_invitations_workspace_id_fk')->references('id')->on('wsp_workspaces')->cascadeOnDelete();
            $table->foreign('invited_by', 'wsp_invitations_invited_by_fk')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE wsp_invitations ADD CONSTRAINT wsp_invitations_status_ck CHECK (status IN ('pending','accepted','revoked','expired'))");
        DB::statement("ALTER TABLE wsp_invitations ALTER COLUMN expires_at SET DEFAULT (now() + interval '7 days')");

        DB::statement('CREATE INDEX wsp_invitations_email_ix ON wsp_invitations (lower(email)) WHERE status = \'pending\'');

        DB::statement("COMMENT ON COLUMN wsp_invitations.role_id IS 'Soft-ref, NO FK (D21): aut_roles lands later with axiomasoft/corex-access (P2.8); cleanup on accept is application-level, not ON DELETE SET NULL'");
    }

    public function down(): void
    {
        $this->drop('wsp_invitations');
    }
};
