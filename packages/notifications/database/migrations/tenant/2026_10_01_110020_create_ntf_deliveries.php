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
            $table->uuid('notification_id')->nullable();
            $table->uuid('digest_run_id')->nullable();
            $table->string('channel', 64);
            $table->char('delivery_key', 64)->unique('ntf_deliveries_delivery_key_uq');
            $table->string('state', 16);
            $table->decimal('claim_epoch', 20, 0);
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
            $table->index(['state', 'next_attempt'], 'ntf_deliveries_scan_ix');
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
