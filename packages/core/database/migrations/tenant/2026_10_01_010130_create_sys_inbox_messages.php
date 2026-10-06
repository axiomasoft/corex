<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_inbox_messages', function (Blueprint $table): void {
            $table->string('consumer', 120);
            $table->uuid('message_id');
            $table->string('name', 160);
            $table->timestampTz('processed_at', precision: 6);
            $table->primary(['consumer', 'message_id'], 'sys_inbox_messages_pk');
            $table->index(['processed_at'], 'sys_inbox_messages_processed_ix');
        });

        Schema::getConnection()->statement('ALTER TABLE sys_inbox_messages RENAME CONSTRAINT sys_inbox_messages_pkey TO sys_inbox_messages_pk');
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_inbox_messages');
    }
};
