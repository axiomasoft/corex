<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id');
            $table->primary(['id'], 'sessions_pk');
            $table->uuid('user_id')->nullable()->index('sessions_user_id_ix');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->bigInteger('last_activity')->index('sessions_last_activity_ix');
        });

        Schema::getConnection()->statement('ALTER TABLE sessions RENAME CONSTRAINT sessions_pkey TO sessions_pk');
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
