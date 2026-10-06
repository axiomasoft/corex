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
        Schema::create('root_accounts', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'root_accounts_pk');
            $table->string('slug', 63)->nullable();
            $table->string('name', 255)->nullable();
            $table->uuid('cluster_id');
            $table->string('db_name', 63);
            $table->string('status', 16)->default('pending');
            $table->uuid('owner_identity_id')->nullable();
            $table->string('vertical_code', 64)->nullable();
            $table->bigInteger('template_version');
            $table->string('embedding_model', 64);
            $table->smallInteger('embedding_dim');
            $table->string('locale', 8)->default('ru');
            $table->timestampTz('trial_ends_at', precision: 6)->nullable();
            $table->timestampTz('suspended_at', precision: 6)->nullable();
            $table->timestampTz('grace_until', precision: 6)->nullable();
            $table->timestampTz('purge_after', precision: 6)->nullable();
            $table->text('signup_key')->nullable();
            $table->text('signup_request_digest')->nullable();
            $table->uuid('signup_cluster_id')->nullable();
            $table->uuid('signup_template_id')->nullable();
            $table->boolean('pool_cleanup')->default(false);
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
            $table->timestampTz('deleted_at', precision: 6)->nullable();

            $table->foreign('cluster_id', 'root_accounts_cluster_id_fk')->references('id')->on('root_clusters')->restrictOnDelete();
            $table->foreign('owner_identity_id', 'root_accounts_owner_identity_id_fk')->references('id')->on('root_identities')->restrictOnDelete();
        });

        Schema::getConnection()->statement('ALTER TABLE root_accounts RENAME CONSTRAINT root_accounts_pkey TO root_accounts_pk');

        DB::statement("ALTER TABLE root_accounts ADD CONSTRAINT root_accounts_status_ck CHECK (status IN ('pending','provisioning','trial','active','suspended','grace','exporting','deleted'))");
        DB::statement("ALTER TABLE root_accounts ADD CONSTRAINT root_accounts_slug_or_pending_ck CHECK (status IN ('pending','provisioning') OR slug IS NOT NULL)");
        DB::statement("ALTER TABLE root_accounts ADD CONSTRAINT root_accounts_owner_or_pending_ck CHECK (status IN ('pending','provisioning') OR owner_identity_id IS NOT NULL)");

        DB::statement('ALTER TABLE root_accounts ADD CONSTRAINT root_accounts_signup_ck CHECK ((signup_key IS NULL AND signup_request_digest IS NULL AND signup_cluster_id IS NULL AND signup_template_id IS NULL) OR (signup_key IS NOT NULL AND signup_request_digest IS NOT NULL AND signup_cluster_id IS NOT NULL AND signup_template_id IS NOT NULL))');
        DB::statement('CREATE UNIQUE INDEX root_accounts_signup_key_uq ON root_accounts (signup_key) WHERE signup_key IS NOT NULL AND deleted_at IS NULL');

        DB::statement('CREATE UNIQUE INDEX root_accounts_slug_uq ON root_accounts (slug) WHERE slug IS NOT NULL AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX root_accounts_db_uq ON root_accounts (db_name)');
        DB::statement('CREATE INDEX root_accounts_cluster_ix ON root_accounts (cluster_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX root_accounts_status_ix ON root_accounts (status) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX root_accounts_purge_ix ON root_accounts (purge_after) WHERE purge_after IS NOT NULL');

        DB::statement("COMMENT ON COLUMN root_accounts.db_name IS 'acc_||hex(id); UNIQUE, никогда не переименовывается'");
        DB::statement("COMMENT ON COLUMN root_accounts.embedding_model IS 'Контракт провижининга R-16: одна активная embedding-модель на БД; смена dim = ALTER+реиндекс, вне happy-path'");
    }

    public function down(): void
    {
        Schema::dropIfExists('root_accounts');
    }
};
