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
        $this->create('sys_lifecycle_plans', function (Blueprint $table): void {
            $table->uuid('plan_id');
            $table->string('account_id', 128);
            $table->string('account_incarnation', 128);
            $table->string('workspace_scope', 138);
            $table->string('module');
            $table->char('request_key', 64);
            $table->string('action', 24);
            $table->string('state', 32);
            $table->char('digest', 64);
            $table->jsonb('scope');
            $table->jsonb('arguments');
            $table->jsonb('inventory');
            $table->jsonb('blockers');
            $table->jsonb('actions');
            $table->jsonb('receipts');
            $table->boolean('cleanup_started')->default(false);
            $table->jsonb('leases');
            $table->jsonb('cursor');
            $table->string('transition_result')->nullable();
            $table->timestampsTz(6);
            $logical = ['account_id', 'account_incarnation', 'workspace_scope', 'module'];
            $table->unique([...$logical, 'request_key'], 'sys_lifecycle_plans_scope_request_uq');
            $table->index([...$logical, 'state'], 'sys_lifecycle_plans_scope_state_ix');
            $table->primary(['plan_id'], 'sys_lifecycle_plans_pk');
        });
    }

    public function down(): void
    {
        $this->drop('sys_lifecycle_plans');
    }
};
