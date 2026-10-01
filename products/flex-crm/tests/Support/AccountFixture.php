<?php

declare(strict_types=1);

namespace Flex\Crm\Tests\Support;

use CoreX\Database\Laravel\IdColumns;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class AccountFixture
{
    public static function migrate(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            IdColumns::primary($table);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->softDeletesTz();
        });
        Schema::create('wsp_workspaces', function (Blueprint $table): void {
            IdColumns::primary($table);
            $table->string('slug')->unique();
            $table->softDeletesTz();
        });
        Schema::create('wsp_workspace_members', function (Blueprint $table): void {
            IdColumns::primary($table);
            IdColumns::foreign($table, 'workspace_id')->constrained('wsp_workspaces')->cascadeOnDelete();
            IdColumns::foreign($table, 'user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->unique(['workspace_id', 'user_id']);
        });
    }
}
