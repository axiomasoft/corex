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
        $this->create('corex_export_parts', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'corex_export_parts_pk');
            $table->uuid('manifest_id');
            $table->decimal('part_no', total: 20, places: 0);
            $table->string('immutable_ref', 1024);
            $table->char('hash', 64);
            $table->decimal('byte_length', total: 20, places: 0);
            $table->jsonb('row_counts');
            $table->string('state', 24);
            $table->unique(['manifest_id', 'part_no'], 'corex_export_parts_manifest_id_part_no_uq');
            $table->foreign('manifest_id', 'corex_export_parts_manifest_id_fk')->references('id')->on('corex_export_manifests')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['corex_export_parts', 'corex_export_manifests'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Export manifests require an approved retention and restore receipt before removal.');
            }
        }

        $this->drop('corex_export_parts');
    }
};
