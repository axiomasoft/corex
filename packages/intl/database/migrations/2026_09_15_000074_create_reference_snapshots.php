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
        Schema::create('corex_reference_datasets', function (Blueprint $table): void {
            $table->string('snapshot_id', 128)->primary();
            $table->json('manifest');
            $table->char('raw_hash', 64);
            $table->char('normalized_hash', 64);
            $table->string('state', 16);
            $table->timestampTz('created_at');
        });

        Schema::create('corex_reference_records', function (Blueprint $table): void {
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
            $table->primary(['snapshot_id', 'namespace', 'source_id']);
            $table->index(['snapshot_id', 'kind', 'normalized_name'], 'corex_reference_records_scan_idx');
        });

        Schema::create('corex_reference_aliases', function (Blueprint $table): void {
            $table->string('snapshot_id', 128);
            $table->string('namespace', 128);
            $table->string('source_id', 255);
            $table->string('locale', 128);
            $table->string('alias', 255);
            $table->string('normalized_alias', 255);
            $table->primary(['snapshot_id', 'namespace', 'source_id', 'locale', 'alias']);
        });

        Schema::create('corex_reference_current', function (Blueprint $table): void {
            $table->string('dataset_key', 128)->primary();
            $table->string('snapshot_id', 128);
            $table->decimal('generation', total: 20, places: 0)->default(0);
        });

        $this->addForeignKeys();
        $this->addDatabaseConstraints();
    }

    public function down(): void
    {
        foreach (['corex_reference_aliases', 'corex_reference_records', 'corex_reference_current', 'corex_reference_datasets'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Reference snapshot tables require an approved retention receipt before removal.');
            }
        }

        Schema::dropIfExists('corex_reference_aliases');
        Schema::dropIfExists('corex_reference_records');
        Schema::dropIfExists('corex_reference_current');
        Schema::dropIfExists('corex_reference_datasets');
    }

    private function addForeignKeys(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE corex_reference_records ADD CONSTRAINT corex_reference_records_snapshot_fk FOREIGN KEY (snapshot_id) REFERENCES corex_reference_datasets (snapshot_id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE corex_reference_records ADD CONSTRAINT corex_reference_records_parent_fk FOREIGN KEY (snapshot_id, parent_namespace, parent_source_id) REFERENCES corex_reference_records (snapshot_id, namespace, source_id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE corex_reference_aliases ADD CONSTRAINT corex_reference_aliases_record_fk FOREIGN KEY (snapshot_id, namespace, source_id) REFERENCES corex_reference_records (snapshot_id, namespace, source_id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE corex_reference_current ADD CONSTRAINT corex_reference_current_snapshot_fk FOREIGN KEY (snapshot_id) REFERENCES corex_reference_datasets (snapshot_id) ON DELETE RESTRICT');

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_reference_records_snapshot_fk BEFORE INSERT ON corex_reference_records BEGIN SELECT CASE WHEN EXISTS (SELECT 1 FROM corex_reference_datasets WHERE snapshot_id = NEW.snapshot_id) THEN 0 ELSE RAISE(ABORT, 'invalid snapshot_id') END; END");
            DB::statement("CREATE TRIGGER corex_reference_current_snapshot_fk BEFORE INSERT ON corex_reference_current BEGIN SELECT CASE WHEN EXISTS (SELECT 1 FROM corex_reference_datasets WHERE snapshot_id = NEW.snapshot_id) THEN 0 ELSE RAISE(ABORT, 'invalid snapshot_id') END; END");
        }
    }

    private function addDatabaseConstraints(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE corex_reference_datasets ADD CONSTRAINT corex_reference_datasets_raw_hash_chk CHECK (raw_hash ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_reference_datasets ADD CONSTRAINT corex_reference_datasets_normalized_hash_chk CHECK (normalized_hash ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_reference_datasets ADD CONSTRAINT corex_reference_datasets_state_chk CHECK (state IN ('staged','published','retired'))");
            DB::statement('ALTER TABLE corex_reference_records ADD CONSTRAINT corex_reference_records_parent_chk CHECK (((parent_namespace IS NULL) = (parent_source_id IS NULL)))');
            DB::statement('ALTER TABLE corex_reference_current ADD CONSTRAINT corex_reference_current_generation_chk CHECK (generation BETWEEN 0 AND 18446744073709551615)');

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_reference_datasets_state_chk BEFORE INSERT ON corex_reference_datasets BEGIN SELECT CASE WHEN NEW.state IN ('staged','published','retired') THEN 0 ELSE RAISE(ABORT, 'invalid state') END; END");
            DB::statement("CREATE TRIGGER corex_reference_records_parent_chk BEFORE INSERT ON corex_reference_records BEGIN SELECT CASE WHEN (NEW.parent_namespace IS NULL) = (NEW.parent_source_id IS NULL) THEN 0 ELSE RAISE(ABORT, 'invalid parent identity') END; END");
        }
    }
};
