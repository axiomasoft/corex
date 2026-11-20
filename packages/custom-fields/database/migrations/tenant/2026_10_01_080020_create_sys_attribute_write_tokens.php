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
        $this->create('sys_attribute_write_tokens', function (Blueprint $table): void {
            $table->string('account_id', 128);
            $table->string('account_incarnation', 128);
            $table->string('workspace_scope', 140);
            $table->string('entity', 128);
            $table->string('actor_kind', 16);
            $table->string('actor_id', 128);
            $table->string('record_id', 128);
            $table->string('operation_key', 191);
            $table->char('request_digest', 64);
            $table->char('schema_revision', 64);
            $table->char('document_revision', 64);
            $table->uuid('message_id');
            $table->uuid('receipt_id');
            $table->primary(['account_id', 'account_incarnation', 'workspace_scope', 'entity', 'actor_kind', 'actor_id', 'record_id', 'operation_key'], 'sys_attribute_write_tokens_pk');
        });
    }

    public function down(): void
    {
        $this->drop('sys_attribute_write_tokens');
    }
};
