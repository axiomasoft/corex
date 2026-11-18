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
        $this->create('corex_search_lanes', function (Blueprint $table): void {
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
    }

    public function down(): void
    {
        $this->drop('corex_search_lanes');
    }
};
