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
        Schema::create('corex_export_manifests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('job_id');
            $table->char('capture_attempt', 64)->unique();
            $table->char('schema_hash', 64);
            $table->char('restore_epoch', 64);
            $table->jsonb('roster');
            $table->char('parts_hash', 64)->nullable();
            $table->string('snapshot_mode', 24);
            $table->string('state', 24);
            $table->foreign('job_id')->references('id')->on('corex_transfer_jobs')->restrictOnDelete();
        });

        Schema::create('corex_export_parts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('manifest_id');
            $table->decimal('part_no', total: 20, places: 0);
            $table->string('immutable_ref', 1024);
            $table->char('hash', 64);
            $table->decimal('byte_length', total: 20, places: 0);
            $table->jsonb('row_counts');
            $table->string('state', 24);
            $table->unique(['manifest_id', 'part_no']);
            $table->foreign('manifest_id')->references('id')->on('corex_export_manifests')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['corex_export_parts', 'corex_export_manifests'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Export manifests require an approved retention and restore receipt before removal.');
            }
        }

        Schema::dropIfExists('corex_export_parts');
        Schema::dropIfExists('corex_export_manifests');
    }
};
