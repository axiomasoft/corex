<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $tableName = 'sys_outbox_messages';

        $this->create($tableName, function (Blueprint $table): void {
            $table->string('account_id', 128);
            $table->string('account_incarnation', 128);
            $table->string('workspace_scope', 140);
            $table->string('deployment_id', 128);
            $table->string('binding_id', 128);
            $table->unsignedBigInteger('binding_generation');
            $table->char('restore_epoch', 64);
            $table->char('digest', 64);
            $table->string('message_id', 128);
            $table->char('scope_seal', 64);
            $table->uuid('claim_token')->nullable();
            $table->unique(['account_id', 'account_incarnation', 'workspace_scope', 'source', 'message_id'], 'sys_outbox_messages_fact_uq');
            $table->uuid('id');
            $table->primary(['id'], 'sys_outbox_messages_pk');
            $table->string('name', 160);
            $table->unsignedSmallInteger('contract_version');
            $table->unsignedSmallInteger('envelope_version');
            $table->string('source', 128);
            $table->json('subject');
            $table->string('correlation_id', 128);
            $table->string('causation_id', 128)->nullable();
            $table->string('pii_class', 16);
            $table->json('scope');
            $table->json('payload');
            $table->json('metadata');
            $table->timestampTz('occurred_at', precision: 6);
            $table->timestampTz('available_at', precision: 6);
            $table->string('status', 16);
            $table->uuid('lease_owner')->nullable();
            $table->timestampTz('lease_until', precision: 6)->nullable();
            $table->unsignedBigInteger('attempts');
            $table->string('failure_class', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('published_at', precision: 6)->nullable();
            $table->timestampTz('dead_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });

        $wrappedTable = Schema::getConnection()->getQueryGrammar()->wrapTable($tableName);

        Schema::getConnection()->statement(
            "CREATE INDEX sys_outbox_messages_claim_ix ON {$wrappedTable} "
            ."(available_at, id) WHERE status = 'pending'",
        );

        Schema::getConnection()->statement(
            "CREATE INDEX sys_outbox_messages_reclaim_ix ON {$wrappedTable} "
            ."(lease_until, id) WHERE status = 'publishing'",
        );

        Schema::getConnection()->statement(
            "CREATE INDEX sys_outbox_messages_published_ix ON {$wrappedTable} "
            ."(published_at, id) WHERE status = 'published'",
        );

        Schema::getConnection()->statement(
            "CREATE INDEX sys_outbox_messages_dead_ix ON {$wrappedTable} "
            ."(dead_at, id) WHERE status = 'dead'",
        );
    }

    public function down(): void
    {
        $this->drop('sys_outbox_messages');
    }
};
