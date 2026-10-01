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
        Schema::create('crm_companies', function (Blueprint $table): void {
            IdColumns::primary($table);
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('inn', 20)->nullable();
            $table->string('kpp', 20)->nullable();
            $table->string('website')->nullable();
            IdColumns::reference($table, 'owner_id')->nullable()->index();
            $table->jsonb('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_companies');
    }
};
