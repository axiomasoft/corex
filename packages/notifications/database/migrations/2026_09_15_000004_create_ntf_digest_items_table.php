<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ntf_digest_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->uuid('notification_id');
            $table->string('channel', 64);
            $table->unique(['notification_id', 'channel']);
            $table->foreign('run_id')->references('id')->on('ntf_digest_runs')->restrictOnDelete();
            $table->foreign('notification_id')->references('id')->on('ntf_notifications')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            Schema::dropIfExists('ntf_digest_items');
        }
    }
};
