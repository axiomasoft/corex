<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 32);
            $table->string('summary');
            $table->text('body')->nullable();
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->index(['subject_type', 'subject_id']);
            $table->uuid('user_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
