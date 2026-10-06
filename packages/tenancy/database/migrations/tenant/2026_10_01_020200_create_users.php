<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'users_pk');
            $table->string('email', 255);
            $table->string('name', 255)->default('');
            $table->string('phone', 32)->nullable();
            $table->string('locale', 8)->default('ru');
            $table->string('password_hash', 255)->nullable();
            $table->uuid('department_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('settings')->default('{}');
            $table->timestampTz('last_login_at', precision: 6)->nullable();
            $table->timestampTz('identity_source_updated_at', precision: 6)->nullable();
            $table->timestampTz('synced_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
            $table->timestampTz('deleted_at', precision: 6)->nullable();
        });

        Schema::getConnection()->statement('ALTER TABLE users RENAME CONSTRAINT users_pkey TO users_pk');

        // Preserve the existing cross-package integration FK in raw DDL.
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_department_fk FOREIGN KEY (department_id) REFERENCES sys_departments (id) ON DELETE SET NULL');

        DB::statement('CREATE UNIQUE INDEX users_email_uq ON users (lower(email)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
