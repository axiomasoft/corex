<?php

declare(strict_types=1);

use CoreX\Database\Laravel\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tsk_tasks', function (Blueprint $table): void {
            IdColumns::primary($table);
            IdColumns::foreign($table, 'process_id')->constrained('tsk_processes')->restrictOnDelete();
            IdColumns::foreign($table, 'stage_id')->constrained('tsk_stages')->restrictOnDelete();
            IdColumns::reference($table, 'project_id')->nullable()->index();
            $table->string('task_key', 100);
            $table->string('title');
            $table->jsonb('description_json')->nullable();
            $table->string('priority', 50)->default('medium');
            IdColumns::reference($table, 'creator_id');
            IdColumns::reference($table, 'responsible_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampTz('due_date')->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->unsignedInteger('spent_minutes')->default(0);
            IdColumns::reference($table, 'parent_id')->nullable()->index();
            $table->nullableUlidMorphs('taskable');
            $table->timestampTz('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['process_id']);
            $table->index(['responsible_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tsk_tasks');
    }
};
