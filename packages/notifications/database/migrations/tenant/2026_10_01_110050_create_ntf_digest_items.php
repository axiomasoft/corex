<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ntf_digest_items', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'ntf_digest_items_pk');
            $table->uuid('run_id');
            $table->uuid('notification_id');
            $table->string('channel', 64);
            $table->unique(['notification_id', 'channel'], 'ntf_digest_items_notification_id_channel_uq');
            $table->foreign('run_id', 'ntf_digest_items_run_id_fk')->references('id')->on('ntf_digest_runs')->restrictOnDelete();
            $table->foreign('notification_id', 'ntf_digest_items_notification_id_fk')->references('id')->on('ntf_notifications')->restrictOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE ntf_digest_items RENAME CONSTRAINT ntf_digest_items_pkey TO ntf_digest_items_pk');
        }
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            Schema::dropIfExists('ntf_digest_items');
        }

    }
};
