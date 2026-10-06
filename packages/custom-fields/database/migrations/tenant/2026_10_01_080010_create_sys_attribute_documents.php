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
            $table->primary(['entity', 'record_id'], 'sys_attribute_documents_pk');
        });
        Schema::getConnection()->statement('ALTER TABLE sys_attribute_documents RENAME CONSTRAINT sys_attribute_documents_pkey TO sys_attribute_documents_pk');
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_attribute_documents');
    }
};
