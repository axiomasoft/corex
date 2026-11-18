<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->create('root_account_events', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'root_account_events_pk');
            $table->uuid('account_id');
            $table->string('event', 64);
            $table->jsonb('actor')->default('{}');
            $table->jsonb('payload')->default('{}');
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->foreign('account_id', 'root_account_events_account_id_fk')->references('id')->on('root_accounts')->cascadeOnDelete();
        });

        DB::statement('CREATE INDEX root_account_events_account_created_ix ON root_account_events (account_id, created_at)');
    }

    public function down(): void
    {
        $this->drop('root_account_events');
    }
};
