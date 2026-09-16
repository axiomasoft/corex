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
            $table->foreign('file_id')->references('id')->on('med_files')->restrictOnDelete();
            $table->unique(['file_id', 'profile_key', 'profile_digest', 'source_version']);
        });
    }
};
