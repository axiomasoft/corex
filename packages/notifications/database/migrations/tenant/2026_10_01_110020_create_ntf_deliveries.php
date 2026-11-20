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
        $this->create('ntf_deliveries', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'ntf_deliveries_pk');
            $table->string('account_id', 128);
            $table->string('account_incarnation', 128);
            $table->string('workspace_scope', 138);
            $table->string('deployment_id', 128);
            $table->string('binding_id', 128);
            $table->unsignedBigInteger('binding_generation');
            $table->char('restore_epoch', 64);
            $table->json('scope');
            $table->char('scope_seal', 64);
            $table->uuid('notification_id')->nullable();
            $table->uuid('digest_run_id')->nullable();
            $table->string('channel', 64);
            $table->char('delivery_key', 64)->unique('ntf_deliveries_delivery_key_uq');
            $table->string('state', 32);
            $table->decimal('claim_epoch', 20, 0);
            $table->uuid('claim_token')->nullable();
            $table->uuid('provider_ticket')->nullable();
            $table->string('provider_namespace', 191)->nullable();
            $table->text('target_reference')->nullable();
            $table->unsignedBigInteger('attempts');
            $table->timestampTz('next_attempt')->nullable();
            $table->timestampTz('lease_until')->nullable();
            $table->decimal('admitted_revision', 20, 0);
            $table->string('provider_ref', 255)->nullable();
            $table->json('error')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->json('message')->nullable();
            $table->char('message_hash', 64)->nullable();
            $table->string('template_version', 128)->nullable();
            $table->string('policy_version', 128)->nullable();
            $table->string('driver_version', 128)->nullable();
            $table->foreign('notification_id', 'ntf_deliveries_notification_id_fk')->references('id')->on('ntf_notifications')->restrictOnDelete();
            $table->unique(['notification_id', 'channel'], 'ntf_deliveries_notification_channel_uq');
            $table->unique(['digest_run_id', 'channel'], 'ntf_deliveries_digest_channel_uq');
            $table->index(['account_id', 'account_incarnation', 'workspace_scope', 'state', 'next_attempt', 'id'], 'ntf_deliveries_scan_ix');
        });
    }

    public function down(): void
    {
        if (! app()->environment('testing') && DB::table('ntf_deliveries')->exists()) {
            throw new RuntimeException('MigrationRollbackDenied: ntf_deliveries contains durable delivery state.');
        }

        $this->drop('ntf_deliveries');
    }
};
