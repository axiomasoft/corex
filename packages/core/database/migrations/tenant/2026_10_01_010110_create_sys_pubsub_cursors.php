<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Durable subscriber positions use the transport's monotonic message cursor.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_pubsub_cursors', function (Blueprint $table): void {
            $table->string('channel', 190);
            $table->string('subscriber', 190);
            $table->unsignedBigInteger('position');
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->primary(['channel', 'subscriber'], 'sys_pubsub_cursors_pk');
        });

        Schema::getConnection()->statement('ALTER TABLE sys_pubsub_cursors RENAME CONSTRAINT sys_pubsub_cursors_pkey TO sys_pubsub_cursors_pk');
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_pubsub_cursors');
    }
};
