<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('med_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('file_id');
            $table->string('operation_key', 128)->unique();
            $table->string('kind', 32);
            $table->string('state', 16);
            $table->string('profile_key', 128)->nullable();
            $table->char('profile_digest', 64)->nullable();
            $table->unsignedBigInteger('source_version')->nullable();
            $table->unsignedBigInteger('claim_epoch')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->string('attempt_object_key')->nullable();
            $table->timestamps();
            $table->foreign('file_id')->references('id')->on('med_files')->restrictOnDelete();
            $table->index(['state', 'next_attempt_at']);
        });
    }
};
