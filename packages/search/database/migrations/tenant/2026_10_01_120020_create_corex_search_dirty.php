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
        $this->create('corex_search_dirty', function (Blueprint $table): void {
            $table->char('lane_key', 64);
            $table->bigInteger('seq');
            $table->string('source', 128);
            $table->string('event_id', 128);
            $table->string('record_id', 128);
            $table->string('operation', 16)->default('reread');
            $table->primary(['lane_key', 'seq'], 'corex_search_dirty_pk');
            $table->unique(['lane_key', 'source', 'event_id', 'record_id'], 'corex_search_dirty_event_record_uq');
        });
    }

    public function down(): void
    {
        $this->drop('corex_search_dirty');
    }
};
