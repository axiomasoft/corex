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
        $this->create('corex_search_generations', function (Blueprint $table): void {
            $table->char('lane_key', 64);
            $table->bigInteger('generation');
            $table->string('cluster', 128);
            $table->string('uid', 128);
            $table->uuid('allocation_id');
            $table->char('mapping_digest', 64);
            $table->char('policy_digest', 64);
            $table->timestampTz('retain_until')->nullable();
            $table->string('state', 24);
            $table->bigInteger('confirmed_seq')->default(0);
            $table->string('scan_cursor', 128)->nullable();
            $table->bigInteger('claim_epoch')->default(0);
            $table->primary(['lane_key', 'generation'], 'corex_search_generations_pk');
            $table->unique(['cluster', 'uid'], 'corex_search_generations_uid_uq');
        });
        $this->db()->statement("create unique index corex_search_generations_active_uq on corex_search_generations(lane_key) where state = 'active'");
    }

    public function down(): void
    {
        $this->drop('corex_search_generations');
    }
};
