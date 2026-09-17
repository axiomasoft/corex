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
        Schema::create('ntf_recipient_state', function (Blueprint $table): void {
            $table->string('kind', 64);
            $table->string('id', 128);
            $table->decimal('preference_revision', 20, 0);
            $table->primary(['kind', 'id']);
        });
    }

    public function down(): void
    {
        if (! app()->environment('testing') && DB::table('ntf_recipient_state')->exists()) {
            throw new RuntimeException('MigrationRollbackDenied: ntf_recipient_state contains durable recipient state.');
        }

        Schema::dropIfExists('ntf_recipient_state');
    }
};
