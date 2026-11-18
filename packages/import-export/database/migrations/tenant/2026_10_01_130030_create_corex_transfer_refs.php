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
        $this->create('corex_transfer_refs', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'corex_transfer_refs_pk');
            $table->uuid('job_id');
            $table->string('entity', 128);
            $table->string('external_system', 128);
            $table->string('external_id', 255);
            $table->string('target_id', 255)->nullable();
            $table->string('source_revision', 255);
            $table->timestampTz('source_updated_at')->nullable();
            $table->char('payload_hash', 64);
            $table->string('state', 24);
            $table->jsonb('result')->nullable();
            $table->timestampsTz();
            $table->unique(['entity', 'external_system', 'external_id'], 'corex_transfer_refs_entity_external_system_external_id_uq');
            $table->foreign('job_id', 'corex_transfer_refs_job_id_fk')->references('id')->on('corex_transfer_jobs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['corex_transfer_outbox', 'corex_transfer_refs', 'corex_transfer_rows', 'corex_transfer_jobs'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Transfer tables require an approved retention and restore receipt before removal.');
            }
        }

        $this->drop('corex_transfer_refs');
    }
};
