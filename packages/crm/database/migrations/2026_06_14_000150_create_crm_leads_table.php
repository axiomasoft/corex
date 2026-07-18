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
        Schema::create('crm_leads', function (Blueprint $table): void {
            IdColumns::primary($table);
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('source', 32);
            $table->string('status', 32)->default('new');
            $table->unsignedTinyInteger('score')->nullable();
            IdColumns::reference($table, 'owner_id')->nullable()->index();
            $table->jsonb('custom_fields')->nullable();
            IdColumns::reference($table, 'converted_contact_id')->nullable();
            IdColumns::reference($table, 'converted_company_id')->nullable();
            IdColumns::reference($table, 'converted_deal_id')->nullable();
            $table->timestampTz('converted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // owner_id already carries a single-column index from its reference
            // above; the tenant-composite it used to pair with is gone (P2.14).
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};
