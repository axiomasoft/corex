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
            $table->uuid('id');
            $table->primary(['id'], 'corex_transfer_jobs_pk');
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
            $table->index(['state', 'updated_at'], 'corex_transfer_jobs_state_updated_at_ix');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE corex_transfer_jobs RENAME CONSTRAINT corex_transfer_jobs_pkey TO corex_transfer_jobs_pk');
        }
    }

    public function down(): void
    {
        foreach (['corex_transfer_outbox', 'corex_transfer_refs', 'corex_transfer_rows', 'corex_transfer_jobs'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Transfer tables require an approved retention and restore receipt before removal.');
            }
        }

        Schema::dropIfExists('corex_transfer_jobs');
    }
};
