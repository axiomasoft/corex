<?php

declare(strict_types=1);

use CoreX\Support\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PG-only (D12), mirrors sys_settings/sys_audit_log/sys_feature_flags —
// lives in migrations/pg/, NOT loaded by CoreServiceProvider::loadMigrationsFrom
// (default sqlite lane). Backs DatabasePollPubSub only (P1.11 — database
// driver of the PubSub contract, B-10 §3.1/§7.3). Driver-local: NOT part of
// the DB_SCHEMA.md canonical catalog / golden template (OQ-6, audit F12).
// `id` is a plain bigint delivery cursor, not a domain entity — UUIDv7
// (ADR-003/D3) does not apply here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Config::pubsubMessagesTable(), function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 190);
            $table->jsonb('payload');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['channel', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::pubsubMessagesTable());
    }
};
