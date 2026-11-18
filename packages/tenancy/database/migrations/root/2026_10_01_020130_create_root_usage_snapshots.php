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
        $this->create('root_usage_snapshots', function (Blueprint $table): void {
            $table->uuid('account_id');
            $table->string('metric', 64);
            $table->date('period');
            $table->decimal('value', 18, 3);
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->primary(['account_id', 'metric', 'period'], 'root_usage_snapshots_pk');
            $table->foreign('account_id', 'root_usage_snapshots_account_id_fk')->references('id')->on('root_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $this->drop('root_usage_snapshots');
    }
};
