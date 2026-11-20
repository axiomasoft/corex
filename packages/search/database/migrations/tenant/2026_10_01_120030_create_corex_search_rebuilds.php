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
            $table->uuid('rebuild_id');
            $table->primary(['rebuild_id'], 'corex_search_rebuilds_pk');
            $table->char('lane_key', 64);
            $table->string('operation_key', 191)->nullable();
            $table->bigInteger('from_generation')->nullable();
            $table->bigInteger('to_generation')->nullable();
            $table->string('state', 24);
            $table->char('mapping_digest', 64);
            $table->char('policy_digest', 64);
            $table->bigInteger('start_seq')->nullable();
            $table->bigInteger('barrier_seq')->nullable();
            $table->uuid('rollback_of')->nullable();
            $table->unique(['lane_key', 'operation_key'], 'corex_search_rebuilds_operation_uq');
        });
        $this->db()->statement('create unique index corex_search_rebuilds_destination_uq on corex_search_rebuilds(lane_key,to_generation) where rollback_of is null');
        $this->db()->statement("create unique index corex_search_rebuilds_live_uq on corex_search_rebuilds(lane_key) where state in ('scanning','catching-up','barrier')");
    }

    public function down(): void
    {
        $this->drop('corex_search_rebuilds');
    }
};
