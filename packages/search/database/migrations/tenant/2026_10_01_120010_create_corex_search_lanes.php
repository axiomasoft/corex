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
            $table->char('lane_key', 64);
            $table->primary(['lane_key'], 'corex_search_lanes_pk');
            $table->string('deployment_id', 128);
            $table->string('binding_id', 128);
            $table->bigInteger('binding_generation');
            $table->char('restore_epoch', 64);
            $table->string('account_id', 128);
            $table->string('account_incarnation', 128);
            $table->string('workspace_scope', 140);
            $table->string('entity', 128);
            $table->string('backend', 128);
            $table->string('cluster', 128);
            $table->bigInteger('active_generation')->nullable();
            $table->bigInteger('writer_generation')->nullable();
            $table->bigInteger('last_generation')->default(0);
            $table->bigInteger('last_allocated_seq')->default(0);
            $table->bigInteger('next_seq')->default(1);
            $table->uuid('rebuild_id')->nullable();
            $table->bigInteger('freeze_seq')->nullable();
        });
    }

    public function down(): void
    {
        $this->drop('corex_search_lanes');
    }
};
