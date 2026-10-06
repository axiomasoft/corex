<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Executed from the package schema set on its declared connection.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_pubsub_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 190);
            $table->jsonb('payload');
            $table->timestampTz('created_at', precision: 6)->useCurrent();

            $table->index(['channel', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_pubsub_messages');
    }
};
