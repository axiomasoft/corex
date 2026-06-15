<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tsk_tasks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id', 64)->nullable()->index();
            $table->foreignUlid('process_id')->constrained('tsk_processes')->restrictOnDelete();
            $table->foreignUlid('stage_id')->constrained('tsk_stages')->restrictOnDelete();
            $table->ulid('project_id')->nullable()->index();
            $table->string('task_key', 100);
            $table->string('title');
            $table->jsonb('description_json')->nullable();
            $table->string('priority', 50)->default('medium');
            $table->string('creator_id', 64);
            $table->string('responsible_id', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampTz('due_date')->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->unsignedInteger('spent_minutes')->default(0);
            $table->ulid('parent_id')->nullable()->index();
            $table->nullableUlidMorphs('taskable');
            $table->timestampTz('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'process_id']);
            $table->index(['tenant_id', 'responsible_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tsk_tasks');
    }
};
