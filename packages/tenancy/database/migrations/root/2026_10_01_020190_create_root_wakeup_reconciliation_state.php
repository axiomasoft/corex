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
        $this->create('root_wakeup_reconciliation_state', function (Blueprint $table): void {
            $table->string('name', 64);
            $table->primary(['name'], 'root_wakeup_reconciliation_state_pk');
            $table->uuid('cursor_account_id')->nullable();
            $table->unsignedBigInteger('attempts')->default(0);
            $table->timestampTz('retry_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });
    }

    public function down(): void
    {
        $this->drop('root_wakeup_reconciliation_state');
    }
};
