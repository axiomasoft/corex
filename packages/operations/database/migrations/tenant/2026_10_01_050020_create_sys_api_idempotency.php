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
        $this->create('sys_api_idempotency', function (Blueprint $table): void {
            $table->char('scope_hash', 64);
            $table->char('key_hash', 64);
            $table->char('request_fingerprint', 64);
            $table->string('state', 16);
            $table->unsignedBigInteger('attempt');
            $table->uuid('lease_token')->nullable();
            $table->timestampTz('lease_until', precision: 6)->nullable();
            $table->json('outcome_json')->nullable();
            $table->char('contract_hash', 64);
            $table->timestampTz('expires_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6);
            $table->timestampTz('updated_at', precision: 6);
            $table->unique(['scope_hash', 'key_hash'], 'sys_api_idempotency_scope_key_uq');
            $table->index(['state', 'expires_at'], 'sys_api_idempotency_retention_ix');
        });
    }

    public function down(): void
    {
        $this->drop('sys_api_idempotency');
    }
};
