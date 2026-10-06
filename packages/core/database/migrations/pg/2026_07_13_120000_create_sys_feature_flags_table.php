<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Database\Laravel\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Executed from the package schema set on its declared connection.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(app(CoreOptions::class)->featureFlagsTable, function (Blueprint $table): void {
            IdColumns::primary($table);
            $table->string('key', 128)->unique();
            $table->string('module', 190)->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(app(CoreOptions::class)->featureFlagsTable);
    }
};
