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
        Schema::create('corex_transfer_rows', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'corex_transfer_rows_pk');
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
            $table->unique(['job_id', 'ordinal'], 'corex_transfer_rows_job_id_ordinal_uq');
            $table->foreign('job_id', 'corex_transfer_rows_job_id_fk')->references('id')->on('corex_transfer_jobs')->restrictOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE corex_transfer_rows RENAME CONSTRAINT corex_transfer_rows_pkey TO corex_transfer_rows_pk');
        }
    }

    public function down(): void
    {
        foreach (['corex_transfer_outbox', 'corex_transfer_refs', 'corex_transfer_rows', 'corex_transfer_jobs'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Transfer tables require an approved retention and restore receipt before removal.');
            }
        }

        Schema::dropIfExists('corex_transfer_rows');
    }
};
