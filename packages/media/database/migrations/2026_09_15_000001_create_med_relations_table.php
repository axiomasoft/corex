<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('med_relations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('file_id');
            $table->string('entity_handle', 128);
            $table->string('entity_id', 128);
            $table->string('slot', 64);
            $table->timestamps();
            $table->foreign('file_id')->references('id')->on('med_files')->restrictOnDelete();
            $table->unique(['file_id', 'entity_handle', 'entity_id', 'slot']);
        });
    }
};
