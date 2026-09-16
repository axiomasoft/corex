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
        Schema::create('ntf_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('recipient_kind', 64);
            $table->string('recipient_id', 128);
            $table->string('request_key', 128);
            $table->char('request_digest', 64);
            $table->string('event_type', 128);
            $table->string('template_key', 128);
            $table->json('payload');
            $table->json('related')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('dismissed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->decimal('revision', 20, 0);
            $table->timestampsTz();
            $table->unique(['recipient_kind', 'recipient_id', 'request_key']);
            $table->index(['recipient_kind', 'recipient_id', 'read_at', 'id'], 'ntf_notifications_scan_idx');
        });
    }

    public function down(): void
    {
        if (! app()->environment('testing') && DB::table('ntf_notifications')->exists()) {
            throw new RuntimeException('MigrationRollbackDenied: ntf_notifications contains durable notification state.');
        }

        Schema::dropIfExists('ntf_notifications');
    }
};
