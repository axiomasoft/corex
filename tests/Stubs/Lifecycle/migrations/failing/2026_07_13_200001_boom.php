<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

// Intentionally always fails (migration K of N).
return new class extends Migration
{
    public function up(): never
    {
        throw new RuntimeException('boom: migration K intentionally fails.');
    }

    public function down(): void {}
};
