<?php

declare(strict_types=1);

namespace Flex\Crm\Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class AccountFixture
{
    public static function migrate(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->softDeletesTz();
        });
        Schema::create('wsp_workspaces', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->softDeletesTz();
        });
        Schema::create('wsp_workspace_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('wsp_workspaces')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->unique(['workspace_id', 'user_id']);
        });
    }
}
