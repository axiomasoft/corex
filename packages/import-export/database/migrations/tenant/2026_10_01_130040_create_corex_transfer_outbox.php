<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->create('corex_transfer_outbox', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'corex_transfer_outbox_pk');
            $table->uuid('job_id');
            $table->char('operation_key', 64)->unique('corex_transfer_outbox_operation_key_uq');
            $table->char('payload_hash', 64);
            $table->jsonb('request');
            $table->string('state', 24);
            $table->decimal('claim_epoch', total: 20, places: 0)->default(0);
            $table->string('provider_task', 255)->nullable();
            $table->timestampTz('dedup_deadline')->nullable();
            $table->timestampsTz();
            $table->foreign('job_id', 'corex_transfer_outbox_job_id_fk')->references('id')->on('corex_transfer_jobs')->restrictOnDelete();
            $table->index(['job_id'], 'corex_transfer_outbox_job_id_ix');
        });
    }

    public function down(): void
    {
        foreach (['corex_transfer_outbox', 'corex_transfer_refs', 'corex_transfer_rows', 'corex_transfer_jobs'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Transfer tables require an approved retention and restore receipt before removal.');
            }
        }

        $this->drop('corex_transfer_outbox');
    }
};
