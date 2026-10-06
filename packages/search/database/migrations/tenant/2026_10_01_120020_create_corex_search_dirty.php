<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            $table->unique(['lane_key', 'record_id', 'revision'], 'corex_search_dirty_lane_key_record_id_revision_uq');
            $table->index(['lane_key', 'submitted_at', 'sequence'], 'corex_search_dirty_lane_key_submitted_at_sequence_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corex_search_dirty');
    }
};
