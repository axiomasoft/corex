<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('root_wakeup_reconciliation_state', function (Blueprint $table): void {
            $table->string('name', 64);
            $table->primary(['name'], 'root_wakeup_reconciliation_state_pk');
            $table->uuid('cursor_account_id')->nullable();
            $table->unsignedBigInteger('attempts')->default(0);
            $table->timestampTz('retry_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });

        Schema::getConnection()->statement('ALTER TABLE root_wakeup_reconciliation_state RENAME CONSTRAINT root_wakeup_reconciliation_state_pkey TO root_wakeup_reconciliation_state_pk');
    }

    public function down(): void
    {
        Schema::dropIfExists('root_wakeup_reconciliation_state');
    }
};
