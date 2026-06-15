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
            $table->ulid('id')->primary();
            $table->string('tenant_id', 64)->nullable()->index();
            $table->string('type', 32);
            $table->string('summary');
            $table->text('body')->nullable();
            $table->ulidMorphs('subject');
            $table->string('user_id', 64)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
