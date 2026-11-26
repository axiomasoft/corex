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
        $this->create('root_impersonation_grants', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'root_impersonation_grants_pk');
            $table->uuid('staff_identity_id');
            $table->uuid('account_id');
            $table->uuid('target_user_id')->nullable();
            $table->text('reason');
            $table->uuid('approved_by')->nullable();
            $table->timestampTz('expires_at', precision: 6)->default(DB::raw("(now() + interval '1 hour')"));
            $table->timestampTz('used_at', precision: 6)->nullable();
            $table->timestampTz('revoked_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->foreign('staff_identity_id', 'root_impersonation_grants_staff_identity_id_fk')->references('id')->on('root_identities')->cascadeOnDelete();
            $table->foreign('account_id', 'root_impersonation_grants_account_id_fk')->references('id')->on('root_accounts')->cascadeOnDelete();
            $table->foreign('approved_by', 'root_impersonation_grants_approved_by_fk')->references('id')->on('root_identities')->nullOnDelete();
        });

        DB::statement('CREATE INDEX root_impersonation_grants_account_created_ix ON root_impersonation_grants (account_id, created_at)');
    }

    public function down(): void
    {
        $this->drop('root_impersonation_grants');
    }
};
