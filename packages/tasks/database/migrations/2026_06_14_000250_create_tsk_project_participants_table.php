<?php

declare(strict_types=1);

use CoreX\Support\Schema\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tsk_project_participants', function (Blueprint $table): void {
            IdColumns::primary($table);
            IdColumns::foreign($table, 'project_id')->constrained('tsk_projects')->cascadeOnDelete();
            IdColumns::reference($table, 'user_id');
            $table->string('role', 50)->default('participant');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tsk_project_participants');
    }
};
