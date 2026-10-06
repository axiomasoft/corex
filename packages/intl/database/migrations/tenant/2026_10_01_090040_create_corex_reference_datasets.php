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
            $table->string('snapshot_id', 128);
            $table->primary(['snapshot_id'], 'corex_reference_datasets_pk');
            $table->json('manifest');
            $table->char('raw_hash', 64);
            $table->char('normalized_hash', 64);
            $table->string('state', 16);
            $table->timestampTz('created_at');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE corex_reference_datasets RENAME CONSTRAINT corex_reference_datasets_pkey TO corex_reference_datasets_pk');
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE corex_reference_datasets ADD CONSTRAINT corex_reference_datasets_raw_hash_ck CHECK (raw_hash ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_reference_datasets ADD CONSTRAINT corex_reference_datasets_normalized_hash_ck CHECK (normalized_hash ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_reference_datasets ADD CONSTRAINT corex_reference_datasets_state_ck CHECK (state IN ('staged','published','retired'))");
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_reference_datasets_state_ck BEFORE INSERT ON corex_reference_datasets BEGIN SELECT CASE WHEN NEW.state IN ('staged','published','retired') THEN 0 ELSE RAISE(ABORT, 'invalid state') END; END");
        }
    }

    public function down(): void
    {
        foreach (['corex_reference_aliases', 'corex_reference_records', 'corex_reference_current', 'corex_reference_datasets'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Reference snapshot tables require an approved retention receipt before removal.');
            }
        }

        Schema::dropIfExists('corex_reference_datasets');
    }
};
