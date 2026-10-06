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
        Schema::create('corex_rate_batches', function (Blueprint $table): void {
            $table->char('id', 26);
            $table->primary(['id'], 'corex_rate_batches_pk');
            $table->char('operation_key', 64)->unique('corex_rate_batches_operation_key_uq');
            $table->char('payload_hash', 64);
            $table->char('format_hash', 64);
            $table->json('source_manifest');
            $table->decimal('generation', total: 20, places: 0)->default(0);
            $table->string('state', 16);
            $table->timestampTz('created_at');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE corex_rate_batches RENAME CONSTRAINT corex_rate_batches_pkey TO corex_rate_batches_pk');
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE corex_rate_batches ADD CONSTRAINT corex_rate_batches_operation_key_ck CHECK (operation_key ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_rate_batches ADD CONSTRAINT corex_rate_batches_payload_hash_ck CHECK (payload_hash ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_rate_batches ADD CONSTRAINT corex_rate_batches_format_hash_ck CHECK (format_hash ~ '^[a-f0-9]{64}$')");
            DB::statement('ALTER TABLE corex_rate_batches ADD CONSTRAINT corex_rate_batches_generation_ck CHECK (generation BETWEEN 0 AND 18446744073709551615)');
            DB::statement("ALTER TABLE corex_rate_batches ADD CONSTRAINT corex_rate_batches_state_ck CHECK (state IN ('staged','published','conflict','failed'))");
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_rate_batches_operation_key_ck BEFORE INSERT ON corex_rate_batches BEGIN SELECT CASE WHEN length(NEW.operation_key) = 64 AND NEW.operation_key NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid operation_key') END; END");
            DB::statement("CREATE TRIGGER corex_rate_batches_state_ck BEFORE INSERT ON corex_rate_batches BEGIN SELECT CASE WHEN NEW.state IN ('staged','published','conflict','failed') THEN 0 ELSE RAISE(ABORT, 'invalid state') END; END");
        }
    }

    public function down(): void
    {
        foreach (['corex_rate_current', 'corex_rate_batches', 'corex_rate_snapshots'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Rate snapshot tables require an approved retention receipt before removal.');
            }
        }

        Schema::dropIfExists('corex_rate_batches');
    }
};
