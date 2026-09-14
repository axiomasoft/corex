<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
        });

        Schema::create('wsp_workspaces', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wsp_workspaces');
        Schema::dropIfExists('users');
    }
};
