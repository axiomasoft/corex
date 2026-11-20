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
        $this->create('sys_workflow_intents', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'sys_workflow_intents_pk');
            $table->string('request_key', 128);
            $table->char('payload_hash', 64);
            $table->string('account_id', 191);
            $table->string('account_incarnation', 191);
            $table->string('workspace_id', 191)->nullable();
            $table->string('workspace_scope', 201);
            $table->string('deployment_id', 191);
            $table->string('binding_id', 191);
            $table->unsignedBigInteger('binding_generation');
            $table->char('restore_epoch', 64);
            $table->json('scope_json');
            $table->char('scope_seal', 64);
            $table->string('module', 128);
            $table->string('kind', 16);
            $table->unique(['account_id', 'account_incarnation', 'workspace_scope', 'module', 'kind', 'request_key'], 'sys_workflow_intents_request_key_uq');
            $table->string('definition_ref', 255);
            $table->string('version_ref', 255);
            $table->string('subject_ref', 255);
            $table->json('payload');
            $table->string('provider_namespace', 96);
            $table->char('effect_key', 64)->unique('sys_workflow_intents_effect_key_uq');
            $table->string('state', 16);
            $table->string('provider_instance', 255)->nullable();
            $table->uuid('provider_ticket')->nullable();
            $table->string('provider_receipt', 255)->nullable();
            $table->uuid('claim_token')->nullable();
            $table->uuid('worker_claim')->nullable();
            $table->unsignedBigInteger('attempt')->default(0);
            $table->timestampTz('due_at', 6)->nullable();
            $table->timestampTz('lease_until', 6)->nullable();
            $table->timestamps(6);
            $table->index(['state', 'due_at', 'id'], 'sys_workflow_intents_state_due_at_ix');
            $table->index(['state', 'lease_until'], 'sys_workflow_intents_state_lease_ix');
        });
    }

    public function down(): void
    {
        $this->drop('sys_workflow_intents');
    }
};
