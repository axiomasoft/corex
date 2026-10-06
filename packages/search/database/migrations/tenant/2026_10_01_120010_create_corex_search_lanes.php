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
            $table->string('lane_key', 128);
            $table->primary(['lane_key'], 'corex_search_lanes_pk');
            $table->string('generation', 128);
            $table->unsignedBigInteger('committed_revision')->default(0);
            $table->string('restore_epoch', 64);
            $table->string('state', 24)->default('ready');
            $table->unsignedBigInteger('claim_epoch')->default(0);
            $table->string('active_task', 255)->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->timestampsTz(6);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE corex_search_lanes RENAME CONSTRAINT corex_search_lanes_pkey TO corex_search_lanes_pk');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('corex_search_lanes');
    }
};
