<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ntf_digest_runs', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'ntf_digest_runs_pk');
            $table->string('recipient_kind', 64);
            $table->string('recipient_id', 128);
            $table->string('period', 16);
            $table->timestampTz('period_start');
            $table->timestampTz('period_end');
            $table->unsignedBigInteger('batch_seq');
            $table->char('membership_hash', 64);
            $table->string('state', 16);
            $table->timestampTz('created_at');
            $table->unique(['recipient_kind', 'recipient_id', 'period', 'period_start', 'batch_seq'], 'ntf_digest_runs_recipient_kind_recipient_id_period__27ecc408_uq');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE ntf_digest_runs RENAME CONSTRAINT ntf_digest_runs_pkey TO ntf_digest_runs_pk');
        }
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            Schema::dropIfExists('ntf_digest_runs');
        }

    }
};
