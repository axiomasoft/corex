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
        Schema::create('root_identity_accounts', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'root_identity_accounts_pk');
            $table->uuid('identity_id');
            $table->uuid('account_id');
            $table->string('status', 16)->default('active');
            $table->string('sync_status', 16)->default('pending');
            $table->timestampTz('synced_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->foreign('identity_id', 'root_identity_accounts_identity_id_fk')->references('id')->on('root_identities')->cascadeOnDelete();
            $table->foreign('account_id', 'root_identity_accounts_account_id_fk')->references('id')->on('root_accounts')->cascadeOnDelete();
            $table->unique(['identity_id', 'account_id'], 'root_identity_accounts_identity_id_account_id_uq');
        });

        Schema::getConnection()->statement('ALTER TABLE root_identity_accounts RENAME CONSTRAINT root_identity_accounts_pkey TO root_identity_accounts_pk');

        DB::statement("ALTER TABLE root_identity_accounts ADD CONSTRAINT root_identity_accounts_status_ck CHECK (status IN ('invited','active','blocked'))");
        DB::statement("ALTER TABLE root_identity_accounts ADD CONSTRAINT root_identity_accounts_sync_status_ck CHECK (sync_status IN ('pending','synced','failed'))");

        DB::statement('CREATE INDEX root_identity_accounts_acc_ix ON root_identity_accounts (account_id)');
        DB::statement("CREATE INDEX root_identity_accounts_sync_ix ON root_identity_accounts (sync_status) WHERE sync_status <> 'synced'");
    }

    public function down(): void
    {
        Schema::dropIfExists('root_identity_accounts');
    }
};
