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
            $table->ulid('id')->primary();
            $table->string('tenant_id', 64)->nullable()->index();
            $table->foreignUlid('pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
            $table->foreignUlid('stage_id')->constrained('crm_stages')->restrictOnDelete();
            $table->string('title');
            $table->bigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('status', 32)->default('open');
            $table->string('owner_id', 64)->nullable()->index();
            $table->unsignedInteger('position')->default(0);
            $table->ulid('contact_id')->nullable()->index();
            $table->ulid('company_id')->nullable()->index();
            $table->jsonb('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Hot path: deals of a tenant by stage (board / pipeline view).
            $table->index(['tenant_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_deals');
    }
};
