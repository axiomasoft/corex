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
        $physical = $this->create('sys_wakeup_requests', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'sys_wakeup_requests_pk');
            $table->string('account_id', 128);
            $table->string('account_incarnation', 128);
            $table->json('data_scope');
            $table->char('scope_seal', 64);
            $table->string('workspace_scope_key', 255);
            $table->string('kind', 128);
            $table->string('request_key', 128);
            $table->char('request_digest', 64);
            $table->timestampTz('fire_at', 6);
            $table->timestampTz('window_start', 6)->nullable();
            $table->timestampTz('window_end', 6)->nullable();
            $table->string('period', 16)->nullable();
            $table->json('payload');
            $table->string('status', 16);
            $table->unsignedBigInteger('generation');
            $table->unsignedBigInteger('attempt');
            $table->uuid('claim_token')->nullable();
            $table->timestampTz('lease_until', 6)->nullable();
            $table->timestampTz('retry_at', 6)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestampTz('completed_at', 6)->nullable();
            $table->timestampsTz();
            $table->unique(['account_id', 'account_incarnation', 'workspace_scope_key', 'kind', 'request_key'], 'sys_wakeup_requests_identity_uq');
        });

        // The sweep orders runnable rows by the instant each one becomes due, which depends on
        // its status (lease expiry, retry time or fire time). Indexing that same expression
        // over runnable rows only lets the claim and next-due queries stop at the first rows
        // that are due instead of reading and sorting every future wakeup of the account.
        // Keep the expression identical to SweepRunner::DUE_TIME: PostgreSQL and SQLite use an
        // expression index only for a query that repeats the same expression.
        $this->db()->statement(<<<SQL
            CREATE INDEX {$physical}_due_ix ON {$physical} (account_id,
                (case when status in ('running', 'cancel-requested') then lease_until when status = 'retry-scheduled' then retry_at else fire_at end), id)
            WHERE status IN ('pending', 'retry-scheduled', 'running', 'cancel-requested')
            SQL);
    }

    public function down(): void
    {
        $this->drop('sys_wakeup_requests');
    }
};
