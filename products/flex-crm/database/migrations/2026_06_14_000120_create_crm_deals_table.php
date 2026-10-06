<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
            $table->foreignUuid('stage_id')->constrained('crm_stages')->restrictOnDelete();
            $table->string('title');
            $table->bigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('status', 32)->default('open');
            $table->uuid('owner_id')->nullable()->index();
            $table->unsignedInteger('position')->default(0);
            $table->uuid('contact_id')->nullable()->index();
            $table->uuid('company_id')->nullable()->index();
            $table->jsonb('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Hot path: deals by stage (board / pipeline view).
            $table->index(['stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_deals');
    }
};
