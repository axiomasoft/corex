<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_attribute_eav_records', function (Blueprint $table): void {
            $table->string('entity', 128);
            $table->string('record_id', 128);
            $table->string('record_version', 64);
            $table->primary(['entity', 'record_id'], 'sys_attribute_eav_records_pk');
        });
        Schema::getConnection()->statement('ALTER TABLE sys_attribute_eav_records RENAME CONSTRAINT sys_attribute_eav_records_pkey TO sys_attribute_eav_records_pk');
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_attribute_eav_records');
    }
};
