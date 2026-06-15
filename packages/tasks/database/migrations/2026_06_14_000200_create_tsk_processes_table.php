<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tsk_processes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id', 64)->nullable()->index();
            $table->string('name');
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->jsonb('settings')->nullable();
            $table->string('created_by_id', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tsk_processes');
    }
};
