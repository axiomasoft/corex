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
        $this->create('corex_reference_records', function (Blueprint $table): void {
            $table->string('snapshot_id', 128);
            $table->string('namespace', 128);
            $table->string('source_id', 255);
            $table->string('kind', 64);
            $table->string('parent_namespace', 128)->nullable();
            $table->string('parent_source_id', 255)->nullable();
            $table->json('names');
            $table->string('normalized_name', 255);
            $table->json('country_key')->nullable();
            $table->json('coordinates')->nullable();
            $table->json('timezones');
            $table->json('provenance');
            $table->primary(['snapshot_id', 'namespace', 'source_id'], 'corex_reference_records_pk');
            $table->index(['snapshot_id', 'kind', 'normalized_name'], 'corex_reference_records_scan_ix');
            $table->index(['snapshot_id', 'parent_namespace', 'parent_source_id'], 'corex_reference_records_parent_ix');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE corex_reference_records ADD CONSTRAINT corex_reference_records_snapshot_fk FOREIGN KEY (snapshot_id) REFERENCES corex_reference_datasets (snapshot_id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE corex_reference_records ADD CONSTRAINT corex_reference_records_parent_fk FOREIGN KEY (snapshot_id, parent_namespace, parent_source_id) REFERENCES corex_reference_records (snapshot_id, namespace, source_id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE corex_reference_records ADD CONSTRAINT corex_reference_records_parent_ck CHECK (((parent_namespace IS NULL) = (parent_source_id IS NULL)))');
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_reference_records_snapshot_fk BEFORE INSERT ON corex_reference_records BEGIN SELECT CASE WHEN EXISTS (SELECT 1 FROM corex_reference_datasets WHERE snapshot_id = NEW.snapshot_id) THEN 0 ELSE RAISE(ABORT, 'invalid snapshot_id') END; END");
            DB::statement("CREATE TRIGGER corex_reference_records_parent_ck BEFORE INSERT ON corex_reference_records BEGIN SELECT CASE WHEN (NEW.parent_namespace IS NULL) = (NEW.parent_source_id IS NULL) THEN 0 ELSE RAISE(ABORT, 'invalid parent identity') END; END");
        }
    }

    public function down(): void
    {
        foreach (['corex_reference_aliases', 'corex_reference_records', 'corex_reference_current', 'corex_reference_datasets'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Reference snapshot tables require an approved retention receipt before removal.');
            }
        }

        $this->drop('corex_reference_records');
    }
};
