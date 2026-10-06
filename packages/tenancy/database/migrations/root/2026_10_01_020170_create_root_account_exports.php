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
        Schema::create('root_account_exports', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'root_account_exports_pk');
            $table->uuid('account_id');
            $table->string('kind', 16);
            $table->string('status', 16)->default('pending');
            $table->jsonb('formats')->default('["pg_dump","csv"]');
            $table->string('storage_path', 512)->nullable();
            $table->timestampTz('url_expires_at', precision: 6)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->timestampTz('purge_after', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->foreign('account_id', 'root_account_exports_account_id_fk')->references('id')->on('root_accounts')->cascadeOnDelete();
        });

        Schema::getConnection()->statement('ALTER TABLE root_account_exports RENAME CONSTRAINT root_account_exports_pkey TO root_account_exports_pk');

        DB::statement("ALTER TABLE root_account_exports ADD CONSTRAINT root_account_exports_kind_ck CHECK (kind IN ('offboarding','pre_purge','manual'))");
        DB::statement("ALTER TABLE root_account_exports ADD CONSTRAINT root_account_exports_status_ck CHECK (status IN ('pending','running','ready','failed','purged'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('root_account_exports');
    }
};
