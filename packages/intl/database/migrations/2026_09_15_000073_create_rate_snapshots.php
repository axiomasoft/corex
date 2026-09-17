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
        Schema::create('corex_rate_snapshots', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->string('schema_version', 32);
            $table->string('base', 3);
            $table->string('quote', 3);
            $table->string('rate_decimal', 64);
            $table->string('valid_on', 10);
            $table->timestampTz('observed_at');
            $table->string('source', 128);
            $table->string('publication_id', 128);
            $table->string('publication_revision', 128);
            $table->char('payload_hash', 64);
            $table->unique(['source', 'publication_id', 'publication_revision', 'base', 'quote', 'valid_on'], 'corex_rate_snapshots_publication_unique');
        });

        Schema::create('corex_rate_batches', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('operation_key', 64)->unique();
            $table->char('payload_hash', 64);
            $table->char('format_hash', 64);
            $table->json('source_manifest');
            $table->decimal('generation', total: 20, places: 0)->default(0);
            $table->string('state', 16);
            $table->timestampTz('created_at');
        });

        Schema::create('corex_rate_current', function (Blueprint $table): void {
            $table->string('base', 3);
            $table->string('quote', 3);
            $table->string('source', 128);
            $table->char('snapshot_id', 26);
            $table->decimal('generation', total: 20, places: 0)->default(0);
            $table->primary(['base', 'quote', 'source']);
        });

        $this->addForeignKeys();
        $this->addDatabaseConstraints();
    }

    public function down(): void
    {
        foreach (['corex_rate_current', 'corex_rate_batches', 'corex_rate_snapshots'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Rate snapshot tables require an approved retention receipt before removal.');
            }
        }

        Schema::dropIfExists('corex_rate_current');
        Schema::dropIfExists('corex_rate_batches');
        Schema::dropIfExists('corex_rate_snapshots');
    }

    private function addForeignKeys(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE corex_rate_current ADD CONSTRAINT corex_rate_current_snapshot_fk FOREIGN KEY (snapshot_id) REFERENCES corex_rate_snapshots (id) ON DELETE RESTRICT');

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_rate_current_snapshot_fk BEFORE INSERT ON corex_rate_current BEGIN SELECT CASE WHEN EXISTS (SELECT 1 FROM corex_rate_snapshots WHERE id = NEW.snapshot_id) THEN 0 ELSE RAISE(ABORT, 'invalid snapshot_id') END; END");
        }
    }

    private function addDatabaseConstraints(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE corex_rate_snapshots ADD CONSTRAINT corex_rate_snapshots_payload_hash_chk CHECK (payload_hash ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_rate_batches ADD CONSTRAINT corex_rate_batches_operation_key_chk CHECK (operation_key ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_rate_batches ADD CONSTRAINT corex_rate_batches_payload_hash_chk CHECK (payload_hash ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_rate_batches ADD CONSTRAINT corex_rate_batches_format_hash_chk CHECK (format_hash ~ '^[a-f0-9]{64}$')");
            DB::statement('ALTER TABLE corex_rate_batches ADD CONSTRAINT corex_rate_batches_generation_chk CHECK (generation BETWEEN 0 AND 18446744073709551615)');
            DB::statement("ALTER TABLE corex_rate_batches ADD CONSTRAINT corex_rate_batches_state_chk CHECK (state IN ('staged','published','conflict','failed'))");
            DB::statement('ALTER TABLE corex_rate_current ADD CONSTRAINT corex_rate_current_generation_chk CHECK (generation BETWEEN 0 AND 18446744073709551615)');

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_rate_snapshots_payload_hash_chk BEFORE INSERT ON corex_rate_snapshots BEGIN SELECT CASE WHEN length(NEW.payload_hash) = 64 AND NEW.payload_hash NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid payload_hash') END; END");
            DB::statement("CREATE TRIGGER corex_rate_batches_operation_key_chk BEFORE INSERT ON corex_rate_batches BEGIN SELECT CASE WHEN length(NEW.operation_key) = 64 AND NEW.operation_key NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid operation_key') END; END");
            DB::statement("CREATE TRIGGER corex_rate_batches_state_chk BEFORE INSERT ON corex_rate_batches BEGIN SELECT CASE WHEN NEW.state IN ('staged','published','conflict','failed') THEN 0 ELSE RAISE(ABORT, 'invalid state') END; END");
        }
    }
};
