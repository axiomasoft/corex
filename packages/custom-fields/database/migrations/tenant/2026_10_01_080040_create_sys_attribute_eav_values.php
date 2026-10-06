<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_attribute_eav_values', function (Blueprint $table): void {
            $table->string('entity', 128);
            $table->string('record_id', 128);
            $table->string('field', 128);
            $table->jsonb('value');
            $table->primary(['entity', 'record_id', 'field'], 'sys_attribute_eav_values_pk');
        });
        Schema::getConnection()->statement('ALTER TABLE sys_attribute_eav_values RENAME CONSTRAINT sys_attribute_eav_values_pkey TO sys_attribute_eav_values_pk');
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_attribute_eav_values');
    }
};
