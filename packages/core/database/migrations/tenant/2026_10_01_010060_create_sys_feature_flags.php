<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_feature_flags', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'sys_feature_flags_pk');
            $table->string('key', 128);
            $table->unique(['key'], 'sys_feature_flags_key_uq');
            $table->string('module', 190)->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });

        Schema::getConnection()->statement('ALTER TABLE sys_feature_flags RENAME CONSTRAINT sys_feature_flags_pkey TO sys_feature_flags_pk');
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_feature_flags');
    }
};
