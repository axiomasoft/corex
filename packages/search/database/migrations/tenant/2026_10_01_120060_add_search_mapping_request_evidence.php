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
        $this->schema()->table('corex_search_rebuilds', static function (Blueprint $table): void {
            $table->char('source_revision', 64)->nullable();
            $table->char('source_mapping_digest', 64)->nullable();
        });
        $this->db()->statement("create unique index corex_search_rebuilds_pending_request_uq on corex_search_rebuilds(lane_key) where state = 'requested'");
    }

    public function down(): void
    {
        if (! $this->schema()->hasColumn('corex_search_rebuilds', 'source_revision')) {
            return;
        }
        $this->db()->statement('drop index if exists corex_search_rebuilds_pending_request_uq');
        $this->db()->table('corex_search_rebuilds')->whereNotNull('source_revision')->delete();
        $this->schema()->table('corex_search_rebuilds', static function (Blueprint $table): void {
            $table->dropColumn(['source_revision', 'source_mapping_digest']);
        });
    }
};
