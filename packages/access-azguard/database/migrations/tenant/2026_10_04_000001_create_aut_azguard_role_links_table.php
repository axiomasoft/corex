<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aut_azguard_role_links', static function (Blueprint $table): void {
            $table->uuid('role_id')->primary();
            $table->unsignedBigInteger('azguard_role_id')->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aut_azguard_role_links');
    }
};
