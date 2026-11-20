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
            $table->string('account_id', 128);
            $table->string('account_incarnation', 128);
            $table->string('workspace_scope', 140);
            $table->string('deployment_id', 128);
            $table->string('binding_id', 128);
            $table->unsignedBigInteger('binding_generation');
            $table->char('restore_epoch', 64);
            $table->char('digest', 64);
            $table->string('source', 128);
            $table->unsignedBigInteger('contract_version');
            $table->unsignedBigInteger('consumer_version');
            $table->string('consumer', 120);
            $table->string('message_id', 128);
            $table->string('name', 160);
            $table->timestampTz('processed_at', precision: 6);
            $table->primary(['account_id', 'account_incarnation', 'workspace_scope', 'consumer', 'source', 'message_id'], 'sys_inbox_messages_pk');
            $table->index(['processed_at'], 'sys_inbox_messages_processed_ix');
        });
    }

    public function down(): void
    {
        $this->drop('sys_inbox_messages');
    }
};
