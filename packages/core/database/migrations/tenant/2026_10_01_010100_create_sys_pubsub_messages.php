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
        $this->create('sys_pubsub_messages', function (Blueprint $table): void {
            $table->bigInteger('id', autoIncrement: true);
            $table->primary(['id'], 'sys_pubsub_messages_pk');
            $table->string('channel', 190);
            $table->jsonb('payload');
            $table->timestampTz('created_at', precision: 6)->useCurrent();

            $table->index(['channel', 'id'], 'sys_pubsub_messages_channel_id_ix');
        });
    }

    public function down(): void
    {
        $this->drop('sys_pubsub_messages');
    }
};
