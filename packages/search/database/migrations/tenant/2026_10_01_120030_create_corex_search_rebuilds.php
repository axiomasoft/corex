<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->create('corex_search_rebuilds', function (Blueprint $table): void {
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
    }

    public function down(): void
    {
        $this->drop('corex_search_rebuilds');
    }
};
