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
        $this->create('sys_wakeup_requests', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'sys_wakeup_requests_pk');
            $table->string('workspace_scope_key', 255);
            $table->string('kind', 128);
            $table->string('request_key', 128);
            $table->char('request_digest', 64);
            $table->timestampTz('fire_at');
            $table->timestampTz('window_start')->nullable();
            $table->timestampTz('window_end')->nullable();
            $table->string('period', 16)->nullable();
            $table->json('payload');
            $table->string('status', 16);
            $table->unsignedBigInteger('generation');
            $table->unsignedBigInteger('attempt');
            $table->timestampTz('lease_until')->nullable();
            $table->timestampTz('retry_at')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['workspace_scope_key', 'kind', 'request_key'], 'sys_wakeup_requests_workspace_scope_key_kind_request_key_uq');
            $table->unique(['workspace_scope_key', 'request_key'], 'sys_wakeup_requests_workspace_scope_key_request_key_uq');
            $table->index(['status', 'retry_at', 'fire_at'], 'sys_wakeup_requests_scan_ix');
        });
    }

    public function down(): void
    {
        $this->drop('sys_wakeup_requests');
    }
};
