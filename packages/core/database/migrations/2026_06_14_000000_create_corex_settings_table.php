<?php

declare(strict_types=1);

use CoreX\Support\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Config::settingsTable(), function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('scope', 20);
            $table->string('scope_id', 64)->nullable();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'scope_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::settingsTable());
    }
};
