<?php

declare(strict_types=1);

use CoreX\Support\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Config::inboxMessagesTable(), function (Blueprint $table): void {
            $table->string('consumer', 120);
            $table->uuid('message_id');
            $table->string('name', 160);
            $table->timestampTz('processed_at', precision: 6);
            $table->primary(['consumer', 'message_id']);
            $table->index(['processed_at'], 'sys_inbox_messages_processed_idx');
        });
    }
};
