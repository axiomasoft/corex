<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_attribute_key_reservations', function (Blueprint $table): void {
            $table->string('entity', 128);
            $table->string('key', 128);
            $table->uuid('attribute_id');
            $table->unsignedBigInteger('current_revision');
            $table->string('role', 16);
            $table->timestampsTz();
            $table->primary(['entity', 'key'], 'sys_attribute_key_reservations_pk');
            $table->index(['attribute_id', 'current_revision'], 'sys_attribute_key_reservations_attribute_id_current_revision_ix');
        });
        Schema::getConnection()->statement('ALTER TABLE sys_attribute_key_reservations RENAME CONSTRAINT sys_attribute_key_reservations_pkey TO sys_attribute_key_reservations_pk');
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_attribute_key_reservations');
    }
};
