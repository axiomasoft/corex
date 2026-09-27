<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_attribute_definitions', function (Blueprint $table): void {
            $table->uuid('attribute_id');
            $table->unsignedBigInteger('revision');
            $table->string('entity', 128);
            $table->string('key', 128);
            $table->string('type', 64);
            $table->string('lifecycle', 16);
            $table->boolean('required');
            $table->boolean('searchable');
            $table->jsonb('constraints');
            $table->jsonb('read_keys');
            $table->jsonb('write_keys');
            $table->jsonb('index_policy');
            $table->jsonb('meta');
            $table->char('definition_hash', 64);
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['attribute_id', 'revision']);
            $table->index(['entity', 'attribute_id', 'revision']);
        });

        Schema::create('sys_attribute_key_reservations', function (Blueprint $table): void {
            $table->string('entity', 128);
            $table->string('key', 128);
            $table->uuid('attribute_id');
            $table->unsignedBigInteger('current_revision');
            $table->string('role', 16);
            $table->timestampsTz();
            $table->primary(['entity', 'key']);
            $table->index(['attribute_id', 'current_revision']);
        });

        Schema::create('sys_attribute_backfills', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('attribute_id');
            $table->unsignedBigInteger('from_revision');
            $table->unsignedBigInteger('to_revision');
            $table->string('entity', 128);
            $table->string('source_key', 128);
            $table->string('target_key', 128);
            $table->string('status', 16);
            $table->string('cursor_record_id', 128)->nullable();
            $table->unsignedBigInteger('attempted')->default(0);
            $table->unsignedBigInteger('changed')->default(0);
            $table->unsignedBigInteger('failed')->default(0);
            $table->char('source_digest', 64)->nullable();
            $table->char('target_digest', 64)->nullable();
            $table->text('error')->nullable();
            $table->timestampsTz();
            $table->index(['attribute_id', 'to_revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_attribute_backfills');
        Schema::dropIfExists('sys_attribute_key_reservations');
        Schema::dropIfExists('sys_attribute_definitions');
    }
};
