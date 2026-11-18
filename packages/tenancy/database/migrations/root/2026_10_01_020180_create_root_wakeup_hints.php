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
        $this->create('root_wakeup_hints', function (Blueprint $table): void {
            // root_accounts uses UUID today. This intentionally has no FK:
            // root hints are advisory and cannot become tenant authority.
            $table->uuid('account_id');
            $table->primary(['account_id'], 'root_wakeup_hints_pk');
            $table->timestampTz('next_fire_at', precision: 6);
            $table->unsignedBigInteger('hint_revision');
            $table->timestampTz('leased_until', precision: 6)->nullable();
            $table->unsignedBigInteger('claim_epoch');
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
            $table->index(['next_fire_at', 'leased_until'], 'root_wakeup_hints_scan_ix');
        });
    }

    public function down(): void
    {
        $this->drop('root_wakeup_hints');
    }
};
