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
        Schema::create('corex_transfer_outbox', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'corex_transfer_outbox_pk');
            $table->uuid('job_id');
            $table->char('operation_key', 64)->unique('corex_transfer_outbox_operation_key_uq');
            $table->char('payload_hash', 64);
            $table->jsonb('request');
            $table->string('state', 24);
            $table->decimal('claim_epoch', total: 20, places: 0)->default(0);
            $table->string('provider_task', 255)->nullable();
            $table->timestampTz('dedup_deadline')->nullable();
            $table->timestampsTz();
            $table->foreign('job_id', 'corex_transfer_outbox_job_id_fk')->references('id')->on('corex_transfer_jobs')->restrictOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE corex_transfer_outbox RENAME CONSTRAINT corex_transfer_outbox_pkey TO corex_transfer_outbox_pk');
        }
    }

    public function down(): void
    {
        foreach (['corex_transfer_outbox', 'corex_transfer_refs', 'corex_transfer_rows', 'corex_transfer_jobs'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Transfer tables require an approved retention and restore receipt before removal.');
            }
        }

        Schema::dropIfExists('corex_transfer_outbox');
    }
};
