<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('med_conversions', function (Blueprint $table): void {
            $table->id();

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->primary(['id'], 'med_conversions_pk');
            }
            $table->uuid('file_id');
            $table->string('profile_key', 128);
            $table->char('profile_digest', 64);
            $table->unsignedBigInteger('source_version');
            $table->string('state', 16);
            $table->string('object_key')->nullable();
            $table->string('mime_type', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
            $table->foreign('file_id', 'med_conversions_file_id_fk')->references('id')->on('med_files')->restrictOnDelete();
            $table->unique(['file_id', 'profile_key', 'profile_digest', 'source_version'], 'med_conversions_file_id_profile_key_profile_digest__67e95e41_uq');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE med_conversions RENAME CONSTRAINT med_conversions_pkey TO med_conversions_pk');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('med_conversions');
    }
};
