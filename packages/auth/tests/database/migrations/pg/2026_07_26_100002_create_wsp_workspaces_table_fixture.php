<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Test-only fixture (D105) — the real `wsp_workspaces` table belongs to
// corex/tenancy (DB_SCHEMA.md §3.4), which corex/auth does not depend on
// (boxed doctrine, AC-28). Only the FK target (uuid PK) is reproduced here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wsp_workspaces', function (Blueprint $table): void {
            $table->uuid('id')->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wsp_workspaces');
    }
};
