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
        $this->create('root_account_schema_state', function (Blueprint $table): void {
            $table->uuid('account_id');
            $table->primary(['account_id'], 'root_account_schema_state_pk');
            $table->bigInteger('current_version');
            $table->bigInteger('target_version');
            $table->string('status', 16)->default('ok');
            $table->uuid('batch_id')->nullable();
            $table->timestampTz('last_migrated_at', precision: 6)->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->foreign('account_id', 'root_account_schema_state_account_id_fk')->references('id')->on('root_accounts')->cascadeOnDelete();
            $table->foreign('batch_id', 'root_account_schema_state_batch_id_fk')->references('id')->on('root_migration_batches')->nullOnDelete();
        });

        DB::statement("ALTER TABLE root_account_schema_state ADD CONSTRAINT root_account_schema_state_status_ck CHECK (status IN ('ok','migrating','failed','blocked'))");

        DB::statement("CREATE INDEX root_account_schema_state_bad_ix ON root_account_schema_state (status) WHERE status <> 'ok'");
    }

    public function down(): void
    {
        $this->drop('root_account_schema_state');
    }
};
