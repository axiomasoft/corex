<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('root_domains', function (Blueprint $table): void {
            $table->unsignedBigInteger('verification_generation')->default(0);
        });
    }
};
