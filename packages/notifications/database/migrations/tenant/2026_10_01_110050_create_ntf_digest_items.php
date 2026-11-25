<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->create('ntf_digest_items', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'ntf_digest_items_pk');
            $table->uuid('run_id');
            $table->uuid('notification_id');
            $table->string('channel', 64);
            $table->unique(['notification_id', 'channel'], 'ntf_digest_items_notification_id_channel_uq');
            $table->foreign('run_id', 'ntf_digest_items_run_id_fk')->references('id')->on('ntf_digest_runs')->restrictOnDelete();
            $table->foreign('notification_id', 'ntf_digest_items_notification_id_fk')->references('id')->on('ntf_notifications')->restrictOnDelete();
            $table->index(['run_id'], 'ntf_digest_items_run_id_ix');
        });
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            $this->drop('ntf_digest_items');
        }
    }
};
