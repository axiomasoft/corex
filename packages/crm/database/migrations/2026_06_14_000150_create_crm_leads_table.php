<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id', 64)->nullable()->index();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('source', 32);
            $table->string('status', 32)->default('new');
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('owner_id', 64)->nullable()->index();
            $table->jsonb('custom_fields')->nullable();
            $table->ulid('converted_contact_id')->nullable();
            $table->ulid('converted_company_id')->nullable();
            $table->ulid('converted_deal_id')->nullable();
            $table->timestampTz('converted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};
