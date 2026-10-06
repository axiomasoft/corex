<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corex_search_rebuilds', function (Blueprint $table): void {
            $table->string('rebuild_id', 128);
            $table->primary(['rebuild_id'], 'corex_search_rebuilds_pk');
            $table->string('lane_key', 128);
            $table->string('from_generation', 128);
            $table->string('to_generation', 128);
            $table->string('restore_epoch', 64);
            $table->char('schema_hash', 64);
            $table->unsignedBigInteger('fence_revision');
            $table->string('state', 24)->default('pending');
            $table->string('swap_task', 255)->nullable();
            $table->char('marker_hash', 64)->nullable();
            $table->timestampTz('swapped_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->unique(['lane_key', 'to_generation'], 'corex_search_rebuilds_lane_key_to_generation_uq');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE corex_search_rebuilds RENAME CONSTRAINT corex_search_rebuilds_pkey TO corex_search_rebuilds_pk');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('corex_search_rebuilds');
    }
};
