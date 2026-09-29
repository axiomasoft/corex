<?php

declare(strict_types=1);

use CoreX\Database\Schema\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tsk_processes', function (Blueprint $table): void {
            IdColumns::primary($table);
            $table->string('name');
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->jsonb('settings')->nullable();
            IdColumns::reference($table, 'created_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tsk_processes');
    }
};
