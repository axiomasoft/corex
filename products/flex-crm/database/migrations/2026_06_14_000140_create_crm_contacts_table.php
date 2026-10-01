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
        Schema::create('crm_contacts', function (Blueprint $table): void {
            IdColumns::primary($table);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            IdColumns::reference($table, 'company_id')->nullable()->index();
            IdColumns::reference($table, 'owner_id')->nullable()->index();
            $table->jsonb('custom_fields')->nullable();
            IdColumns::reference($table, 'merged_into_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
    }
};
