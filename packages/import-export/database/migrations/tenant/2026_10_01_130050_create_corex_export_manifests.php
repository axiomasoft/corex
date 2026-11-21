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
        $this->create('corex_export_manifests', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'corex_export_manifests_pk');
            $table->uuid('job_id')->unique('corex_export_manifests_job_uq');
            $table->char('capture_attempt', 64)->unique('corex_export_manifests_capture_attempt_uq');
            $table->char('schema_hash', 64);
            $table->char('restore_epoch', 64);
            $table->jsonb('roster');
            $table->char('parts_hash', 64)->nullable();
            $table->string('snapshot_mode', 24);
            $table->string('state', 24);
            $table->timestampTz('published_at')->nullable();
            $table->foreign('job_id', 'corex_export_manifests_job_id_fk')->references('id')->on('corex_transfer_jobs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['corex_export_parts', 'corex_export_manifests'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Export manifests require an approved retention and restore receipt before removal.');
            }
        }

        $this->drop('corex_export_manifests');
    }
};
