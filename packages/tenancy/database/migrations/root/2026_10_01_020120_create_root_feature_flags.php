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
        Schema::create('root_feature_flags', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'root_feature_flags_pk');
            $table->uuid('account_id')->nullable();
            $table->string('flag', 64);
            $table->jsonb('value');
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->foreign('account_id', 'root_feature_flags_account_id_fk')->references('id')->on('root_accounts')->cascadeOnDelete();
        });

        Schema::getConnection()->statement('ALTER TABLE root_feature_flags RENAME CONSTRAINT root_feature_flags_pkey TO root_feature_flags_pk');

        // PG16: NULL account_id (global flag) is treated as a value, not "any".
        DB::statement('ALTER TABLE root_feature_flags ADD CONSTRAINT root_feature_flags_uq UNIQUE NULLS NOT DISTINCT (account_id, flag)');
    }

    public function down(): void
    {
        Schema::dropIfExists('root_feature_flags');
    }
};
