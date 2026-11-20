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
        $this->create('sys_attribute_schema_operations', function (Blueprint $table): void {
            $table->string('account_id', 128);
            $table->string('account_incarnation', 128);
            $table->string('workspace_scope', 140);
            $table->string('entity', 128);
            $table->string('deployment_id', 128);
            $table->string('binding_id', 128);
            $table->bigInteger('binding_generation');
            $table->char('restore_epoch', 64);
            $table->uuid('id');
            $table->primary(['id'], 'sys_attribute_schema_operations_pk');
            $table->string('operation_key', 200);
            $table->jsonb('plan');
            $table->char('plan_digest', 64);
            $table->char('base_revision', 64);
            $table->char('target_revision', 64);
            $table->jsonb('scope');
            $table->char('scope_seal', 64)->nullable();
            $table->uuid('requested_message');
            $table->uuid('changed_message');
            $table->string('state', 24);
            $table->string('phase', 24);
            $table->bigInteger('generation')->default(0);
            $table->uuid('claim_token')->nullable();
            $table->timestampTz('lease_until', 6)->nullable();
            $table->timestampTz('next_attempt_at', 6)->useCurrent();
            $table->boolean('cancel_requested')->default(false);
            $table->jsonb('ddl_intent')->nullable();
            $table->jsonb('ddl_receipt')->nullable();
            $table->jsonb('blockers');
            $table->unique(['account_id', 'account_incarnation', 'workspace_scope', 'entity', 'operation_key'], 'sys_attribute_schema_operations_key_uq');
            $table->index(['state', 'next_attempt_at', 'id'], 'sys_attribute_schema_operations_due_ix');
            $table->index(['state', 'lease_until', 'id'], 'sys_attribute_schema_operations_lease_ix');
        });
    }

    public function down(): void
    {
        $this->drop('sys_attribute_schema_operations');
    }
};
