<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Test-only fixture (D105) — the real `users` table belongs to corex/tenancy
// (DB_SCHEMA.md §3.4), which corex/access does not depend on (boxed doctrine,
// AC-28). Only the FK target (uuid PK) is reproduced here, not the full
// column set. `department_id` (P2.9, DB_SCHEMA §3.4: uuid nullable) closes
// the P2.20 debt of dept/dept_tree never having run against a real users
// table — {@see \CoreX\Auth\Tests\PgTestCase::migrateCoreDepartments()}
// supplies the matching `sys_departments` table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('department_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
