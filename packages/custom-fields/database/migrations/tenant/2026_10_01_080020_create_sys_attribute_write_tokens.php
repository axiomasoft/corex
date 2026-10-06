<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_attribute_write_tokens', function (Blueprint $table): void {
            $table->string('operation_key', 128);
            $table->primary(['operation_key'], 'sys_attribute_write_tokens_pk');
            $table->string('entity', 128);
            $table->string('record_id', 128);
            $table->string('payload_hash', 64);
            $table->string('record_version', 64);
        });
        Schema::getConnection()->statement('ALTER TABLE sys_attribute_write_tokens RENAME CONSTRAINT sys_attribute_write_tokens_pkey TO sys_attribute_write_tokens_pk');
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_attribute_write_tokens');
    }
};
