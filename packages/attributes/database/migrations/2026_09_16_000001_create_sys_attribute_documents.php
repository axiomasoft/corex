<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_attribute_documents', function (Blueprint $table): void {
            $table->string('entity', 128);
            $table->string('record_id', 128);
            $table->jsonb('values');
            $table->string('record_version', 64);
            $table->primary(['entity', 'record_id']);
        });

        Schema::create('sys_attribute_write_tokens', function (Blueprint $table): void {
            $table->string('operation_key', 128)->primary();
            $table->string('entity', 128);
            $table->string('record_id', 128);
            $table->string('payload_hash', 64);
            $table->string('record_version', 64);
        });

        Schema::create('sys_attribute_eav_records', function (Blueprint $table): void {
            $table->string('entity', 128);
            $table->string('record_id', 128);
            $table->string('record_version', 64);
            $table->primary(['entity', 'record_id']);
        });

        Schema::create('sys_attribute_eav_values', function (Blueprint $table): void {
            $table->string('entity', 128);
            $table->string('record_id', 128);
            $table->string('field', 128);
            $table->jsonb('value');
            $table->primary(['entity', 'record_id', 'field']);
        });

        Schema::create('sys_attribute_schema_operations', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('operation_key', 128)->unique();
            $table->string('payload_hash', 64);
            $table->string('entity', 128);
            $table->string('schema_hash', 64);
            $table->string('approval_digest', 64);
            $table->string('restore_epoch', 64);
            $table->jsonb('definition');
            $table->string('status', 16);
            $table->unsignedInteger('attempts')->default(0);
            $table->string('error_code', 128)->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_attribute_schema_operations');
        Schema::dropIfExists('sys_attribute_write_tokens');
        Schema::dropIfExists('sys_attribute_documents');
        Schema::dropIfExists('sys_attribute_eav_values');
        Schema::dropIfExists('sys_attribute_eav_records');
    }
};
