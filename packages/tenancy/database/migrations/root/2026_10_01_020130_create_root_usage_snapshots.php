<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('root_usage_snapshots', function (Blueprint $table): void {
            $table->uuid('account_id');
            $table->string('metric', 64);
            $table->date('period');
            $table->decimal('value', 18, 3);
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->primary(['account_id', 'metric', 'period'], 'root_usage_snapshots_pk');
            $table->foreign('account_id', 'root_usage_snapshots_account_id_fk')->references('id')->on('root_accounts')->cascadeOnDelete();
        });

        Schema::getConnection()->statement('ALTER TABLE root_usage_snapshots RENAME CONSTRAINT root_usage_snapshots_pkey TO root_usage_snapshots_pk');
    }

    public function down(): void
    {
        Schema::dropIfExists('root_usage_snapshots');
    }
};
