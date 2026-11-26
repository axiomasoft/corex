<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Test-only fixture — a minimal record shape (owner_id/workspace_id)
// for AccessChecker::canOnRecord() scope-predicate tests (own/workspace/
// account). dept/dept_tree are not exercised against a real record here —
// they require users/sys_departments, a documented boxed-profile limitation
// (README "Известное ограничение профиля").
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('owner_id')->nullable();
            $table->uuid('workspace_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
