<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corex_search_lanes', function (Blueprint $table): void {
            $table->string('lane_key', 128)->primary();
            $table->string('generation', 128);
            $table->unsignedBigInteger('committed_revision')->default(0);
            $table->string('restore_epoch', 64);
            $table->string('state', 24)->default('ready');
            $table->unsignedBigInteger('claim_epoch')->default(0);
            $table->string('active_task', 255)->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->timestampsTz(6);
        });

        Schema::create('corex_search_dirty', function (Blueprint $table): void {
            $table->bigIncrements('sequence');
            $table->string('lane_key', 128);
            $table->string('record_id', 128);
            $table->unsignedBigInteger('revision');
            $table->boolean('tombstone')->default(false);
            $table->string('restore_epoch', 64);
            $table->char('schema_hash', 64)->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->timestampTz('submitted_at', 6)->nullable();
            $table->timestampTz('completed_at', 6)->nullable();
            $table->unique(['lane_key', 'record_id', 'revision']);
            $table->index(['lane_key', 'submitted_at', 'sequence']);
        });

        Schema::create('corex_search_rebuilds', function (Blueprint $table): void {
            $table->string('rebuild_id', 128)->primary();
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
            $table->unique(['lane_key', 'to_generation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corex_search_rebuilds');
        Schema::dropIfExists('corex_search_dirty');
        Schema::dropIfExists('corex_search_lanes');
    }
};
