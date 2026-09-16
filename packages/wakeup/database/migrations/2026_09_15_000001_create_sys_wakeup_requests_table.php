<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_wakeup_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('workspace_scope_key', 160);
            $table->string('kind', 64);
            $table->string('request_key', 255);
            $table->char('request_digest', 64);
            $table->timestampTz('fire_at');
            $table->timestampTz('window_start')->nullable();
            $table->timestampTz('window_end')->nullable();
            $table->string('period', 16)->nullable();
            $table->json('payload');
            $table->string('status', 16);
            $table->unsignedBigInteger('generation');
            $table->unsignedInteger('attempt');
            $table->timestampTz('lease_until')->nullable();
            $table->timestampTz('retry_at')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['workspace_scope_key', 'kind', 'request_key']);
            $table->index(['status', 'retry_at', 'fire_at'], 'sys_wakeup_due_idx');
        });
    }
};
