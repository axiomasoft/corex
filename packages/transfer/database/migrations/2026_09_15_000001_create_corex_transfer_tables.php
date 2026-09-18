<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corex_transfer_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->jsonb('source_manifest');
            $table->char('source_hash', 64);
            $table->jsonb('mapping_snapshot');
            $table->char('mapping_hash', 64);
            $table->char('schema_hash', 64);
            $table->char('restore_epoch', 64);
            $table->string('mode', 24);
            $table->string('state', 24);
            $table->decimal('checkpoint_seq', total: 20, places: 0)->default(0);
            $table->jsonb('cursor');
            $table->jsonb('stats');
            $table->decimal('claim_epoch', total: 20, places: 0)->default(0);
            $table->timestampsTz();
            $table->index(['state', 'updated_at']);
        });

        Schema::create('corex_transfer_rows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('job_id');
            $table->decimal('ordinal', total: 20, places: 0);
            $table->char('external_identity_hash', 64);
            $table->char('operation_key', 64);
            $table->char('payload_hash', 64);
            $table->string('result', 24);
            $table->jsonb('error')->nullable();
            $table->jsonb('request')->nullable();
            $table->string('provider_task', 255)->nullable();
            $table->decimal('epoch', total: 20, places: 0);
            $table->unique(['job_id', 'ordinal']);
            $table->foreign('job_id')->references('id')->on('corex_transfer_jobs')->restrictOnDelete();
        });

        Schema::create('corex_transfer_refs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('job_id');
            $table->string('entity', 128);
            $table->string('external_system', 128);
            $table->string('external_id', 255);
            $table->string('target_id', 255)->nullable();
            $table->string('source_revision', 255);
            $table->timestampTz('source_updated_at')->nullable();
            $table->char('payload_hash', 64);
            $table->string('state', 24);
            $table->jsonb('result')->nullable();
            $table->timestampsTz();
            $table->unique(['entity', 'external_system', 'external_id']);
            $table->foreign('job_id')->references('id')->on('corex_transfer_jobs')->restrictOnDelete();
        });

        Schema::create('corex_transfer_outbox', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('job_id');
            $table->char('operation_key', 64)->unique();
            $table->char('payload_hash', 64);
            $table->jsonb('request');
            $table->string('state', 24);
            $table->decimal('claim_epoch', total: 20, places: 0)->default(0);
            $table->string('provider_task', 255)->nullable();
            $table->timestampTz('dedup_deadline')->nullable();
            $table->timestampsTz();
            $table->foreign('job_id')->references('id')->on('corex_transfer_jobs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['corex_transfer_outbox', 'corex_transfer_refs', 'corex_transfer_rows', 'corex_transfer_jobs'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Transfer tables require an approved retention and restore receipt before removal.');
            }
        }

        Schema::dropIfExists('corex_transfer_outbox');
        Schema::dropIfExists('corex_transfer_refs');
        Schema::dropIfExists('corex_transfer_rows');
        Schema::dropIfExists('corex_transfer_jobs');
    }
};
