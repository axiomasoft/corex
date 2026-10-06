<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('root_account_schema_state', function (Blueprint $table): void {
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

        Schema::getConnection()->statement('ALTER TABLE root_account_schema_state RENAME CONSTRAINT root_account_schema_state_pkey TO root_account_schema_state_pk');

        DB::statement("ALTER TABLE root_account_schema_state ADD CONSTRAINT root_account_schema_state_status_ck CHECK (status IN ('ok','migrating','failed','blocked'))");

        DB::statement("CREATE INDEX root_account_schema_state_bad_ix ON root_account_schema_state (status) WHERE status <> 'ok'");

        DB::statement("COMMENT ON COLUMN root_account_schema_state.status IS 'migrating = сигнал для B-15: DDL-воркер кастомных полей аккаунта откладывает ops (R-13 §3.5); failed→blocked = аккаунт исключён из волн до разбора, код обслуживает совместимой версией'");
    }

    public function down(): void
    {
        Schema::dropIfExists('root_account_schema_state');
    }
};
