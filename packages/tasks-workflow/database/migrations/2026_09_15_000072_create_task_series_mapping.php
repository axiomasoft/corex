<?php

declare(strict_types=1);

use CoreX\Database\Schema\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corex_task_series', function (Blueprint $table): void {
            IdColumns::primary($table);
            $table->string('series_key', 128);
            $table->string('immutable_revision', 128);
            $table->char('definition_hash', 64);
            $table->json('definition');
            $table->json('template');
            $table->json('actor');
            $table->char('context_hash', 64);
            $table->char('restore_epoch', 64);
            $table->unique(['series_key', 'immutable_revision']);
        });

        Schema::create('corex_task_series_state', function (Blueprint $table): void {
            $table->string('series_key', 128)->primary();
            $table->string('active_revision', 128);
            $table->boolean('retired')->default(false);
            $table->string('complete_through', 128)->nullable();
            $table->json('cursor')->nullable();
            $table->char('restore_epoch', 64);
            $table->decimal('generation', total: 20, places: 0)->default(0);
        });

        Schema::create('corex_task_occurrences', function (Blueprint $table): void {
            IdColumns::primary($table);
            $table->string('series_key', 128);
            $table->string('immutable_revision', 128);
            $table->char('occurrence_key', 64);
            $table->json('original_identity');
            $table->string('effective_at', 128);
            $table->json('task_ref')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->string('state', 24);
            $table->unique(['series_key', 'immutable_revision', 'occurrence_key']);
        });

        $this->addForeignKeys();
        $this->addDatabaseConstraints();
    }

    public function down(): void
    {
        foreach (['corex_task_occurrences', 'corex_task_series_state', 'corex_task_series'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Task series mapping requires an approved retention receipt before removal.');
            }
        }

        Schema::dropIfExists('corex_task_occurrences');
        Schema::dropIfExists('corex_task_series_state');
        Schema::dropIfExists('corex_task_series');
    }

    private function addForeignKeys(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE corex_task_series_state ADD CONSTRAINT corex_task_series_state_revision_fk FOREIGN KEY (series_key, active_revision) REFERENCES corex_task_series (series_key, immutable_revision) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE corex_task_occurrences ADD CONSTRAINT corex_task_occurrences_revision_fk FOREIGN KEY (series_key, immutable_revision) REFERENCES corex_task_series (series_key, immutable_revision) ON DELETE RESTRICT');

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_task_series_state_revision_fk BEFORE INSERT ON corex_task_series_state BEGIN SELECT CASE WHEN EXISTS (SELECT 1 FROM corex_task_series WHERE series_key = NEW.series_key AND immutable_revision = NEW.active_revision) THEN 0 ELSE RAISE(ABORT, 'invalid active revision') END; END");
            DB::statement("CREATE TRIGGER corex_task_occurrences_revision_fk BEFORE INSERT ON corex_task_occurrences BEGIN SELECT CASE WHEN EXISTS (SELECT 1 FROM corex_task_series WHERE series_key = NEW.series_key AND immutable_revision = NEW.immutable_revision) THEN 0 ELSE RAISE(ABORT, 'invalid occurrence revision') END; END");
        }
    }

    private function addDatabaseConstraints(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE corex_task_series ADD CONSTRAINT corex_task_series_definition_hash_chk CHECK (definition_hash ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_task_series ADD CONSTRAINT corex_task_series_context_hash_chk CHECK (context_hash ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_task_series ADD CONSTRAINT corex_task_series_restore_epoch_chk CHECK (restore_epoch ~ '^[a-f0-9]{64}$')");
            DB::statement('ALTER TABLE corex_task_series_state ADD CONSTRAINT corex_task_series_state_retired_chk CHECK (retired IN (TRUE, FALSE))');
            DB::statement("ALTER TABLE corex_task_series_state ADD CONSTRAINT corex_task_series_state_restore_epoch_chk CHECK (restore_epoch ~ '^[a-f0-9]{64}$')");
            DB::statement('ALTER TABLE corex_task_series_state ADD CONSTRAINT corex_task_series_state_generation_chk CHECK (generation BETWEEN 0 AND 18446744073709551615)');
            DB::statement("ALTER TABLE corex_task_occurrences ADD CONSTRAINT corex_task_occurrences_occurrence_key_chk CHECK (occurrence_key ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_task_occurrences ADD CONSTRAINT corex_task_occurrences_state_chk CHECK (state IN ('pending','committed','cancelled','retired','unknown'))");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_task_series_definition_hash_chk BEFORE INSERT ON corex_task_series BEGIN SELECT CASE WHEN length(NEW.definition_hash) = 64 AND NEW.definition_hash NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid definition_hash') END; END");
            DB::statement("CREATE TRIGGER corex_task_series_context_hash_chk BEFORE INSERT ON corex_task_series BEGIN SELECT CASE WHEN length(NEW.context_hash) = 64 AND NEW.context_hash NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid context_hash') END; END");
            DB::statement("CREATE TRIGGER corex_task_series_restore_epoch_chk BEFORE INSERT ON corex_task_series BEGIN SELECT CASE WHEN length(NEW.restore_epoch) = 64 AND NEW.restore_epoch NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid restore_epoch') END; END");
            DB::statement("CREATE TRIGGER corex_task_series_state_retired_chk BEFORE INSERT ON corex_task_series_state BEGIN SELECT CASE WHEN NEW.retired IN (0, 1) THEN 0 ELSE RAISE(ABORT, 'invalid retired') END; END");
            DB::statement("CREATE TRIGGER corex_task_series_state_restore_epoch_chk BEFORE INSERT ON corex_task_series_state BEGIN SELECT CASE WHEN length(NEW.restore_epoch) = 64 AND NEW.restore_epoch NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid restore_epoch') END; END");
            DB::statement("CREATE TRIGGER corex_task_series_state_generation_chk BEFORE INSERT ON corex_task_series_state BEGIN SELECT CASE WHEN length(NEW.generation) <= 20 AND NEW.generation NOT GLOB '*[^0-9]*' AND NEW.generation <= '18446744073709551615' THEN 0 ELSE RAISE(ABORT, 'invalid generation') END; END");
            DB::statement("CREATE TRIGGER corex_task_occurrences_occurrence_key_chk BEFORE INSERT ON corex_task_occurrences BEGIN SELECT CASE WHEN length(NEW.occurrence_key) = 64 AND NEW.occurrence_key NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid occurrence_key') END; END");
            DB::statement("CREATE TRIGGER corex_task_occurrences_state_chk BEFORE INSERT ON corex_task_occurrences BEGIN SELECT CASE WHEN NEW.state IN ('pending','committed','cancelled','retired','unknown') THEN 0 ELSE RAISE(ABORT, 'invalid state') END; END");
        }
    }
};
