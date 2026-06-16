<?php

declare(strict_types=1);

use CoreX\Support\Config;
use CoreX\Support\Schema\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Config::settingsTable(), function (Blueprint $table): void {
            IdColumns::primary($table);
            $table->string('scope', 20);
            IdColumns::reference($table, 'scope_id')->nullable();
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
