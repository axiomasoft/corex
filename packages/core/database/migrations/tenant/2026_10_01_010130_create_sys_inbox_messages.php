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
        $this->create('sys_inbox_messages', function (Blueprint $table): void {
            $table->string('consumer', 120);
            $table->uuid('message_id');
            $table->string('name', 160);
            $table->timestampTz('processed_at', precision: 6);
            $table->primary(['consumer', 'message_id'], 'sys_inbox_messages_pk');
            $table->index(['processed_at'], 'sys_inbox_messages_processed_ix');
        });
    }

    public function down(): void
    {
        $this->drop('sys_inbox_messages');
    }
};
