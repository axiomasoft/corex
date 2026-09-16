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
            $table->uuid('id')->primary();
            $table->string('recipient_kind', 64);
            $table->string('recipient_id', 128);
            $table->string('period', 16);
            $table->timestampTz('period_start');
            $table->timestampTz('period_end');
            $table->unsignedInteger('batch_seq');
            $table->char('membership_hash', 64);
            $table->string('state', 16);
            $table->timestampTz('created_at');
            $table->unique(['recipient_kind', 'recipient_id', 'period', 'period_start', 'batch_seq']);
        });
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            Schema::dropIfExists('ntf_digest_runs');
        }
    }
};
