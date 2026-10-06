<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_pubsub_messages', function (Blueprint $table): void {
            $table->bigInteger('id', autoIncrement: true);
            $table->primary(['id'], 'sys_pubsub_messages_pk');
            $table->string('channel', 190);
            $table->jsonb('payload');
            $table->timestampTz('created_at', precision: 6)->useCurrent();

            $table->index(['channel', 'id'], 'sys_pubsub_messages_channel_id_ix');
        });

        Schema::getConnection()->statement('ALTER TABLE sys_pubsub_messages RENAME CONSTRAINT sys_pubsub_messages_pkey TO sys_pubsub_messages_pk');
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_pubsub_messages');
    }
};
