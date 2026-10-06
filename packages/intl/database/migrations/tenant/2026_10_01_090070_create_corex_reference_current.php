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
        Schema::create('corex_reference_current', function (Blueprint $table): void {
            $table->string('dataset_key', 128);
            $table->primary(['dataset_key'], 'corex_reference_current_pk');
            $table->string('snapshot_id', 128);
            $table->decimal('generation', total: 20, places: 0)->default(0);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE corex_reference_current RENAME CONSTRAINT corex_reference_current_pkey TO corex_reference_current_pk');
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE corex_reference_current ADD CONSTRAINT corex_reference_current_snapshot_fk FOREIGN KEY (snapshot_id) REFERENCES corex_reference_datasets (snapshot_id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE corex_reference_current ADD CONSTRAINT corex_reference_current_generation_ck CHECK (generation BETWEEN 0 AND 18446744073709551615)');
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_reference_current_snapshot_fk BEFORE INSERT ON corex_reference_current BEGIN SELECT CASE WHEN EXISTS (SELECT 1 FROM corex_reference_datasets WHERE snapshot_id = NEW.snapshot_id) THEN 0 ELSE RAISE(ABORT, 'invalid snapshot_id') END; END");
        }
    }

    public function down(): void
    {
        foreach (['corex_reference_aliases', 'corex_reference_records', 'corex_reference_current', 'corex_reference_datasets'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Reference snapshot tables require an approved retention receipt before removal.');
            }
        }

        Schema::dropIfExists('corex_reference_current');
    }
};
