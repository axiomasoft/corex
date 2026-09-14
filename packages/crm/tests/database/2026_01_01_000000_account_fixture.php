<?php

declare(strict_types=1);

use Flex\Crm\Tests\Support\AccountFixture;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        AccountFixture::migrate();
    }

    public function down(): void
    {
        Schema::dropIfExists('wsp_workspace_members');
        Schema::dropIfExists('wsp_workspaces');
        Schema::dropIfExists('users');
    }
};
