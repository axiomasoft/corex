<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tsk_task_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('tsk_tasks')->cascadeOnDelete();
            $table->uuid('user_id');
            $table->string('role', 50);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['task_id', 'user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tsk_task_participants');
    }
};
