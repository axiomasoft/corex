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
        $this->create('corex_task_occurrences', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'corex_task_occurrences_pk');
            $table->string('series_key', 128);
            $table->string('immutable_revision', 128);
            $table->char('occurrence_key', 64);
            $table->json('original_identity');
            $table->string('effective_at', 128);
            $table->json('task_ref')->nullable();
            $table->string('operation_key', 128)->unique('corex_task_occurrences_operation_key_uq');
            $table->string('state', 24);
            $table->unique(['series_key', 'immutable_revision', 'occurrence_key'], 'corex_task_occurrences_series_key_immutable_revisio_54d57fbe_uq');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE corex_task_occurrences ADD CONSTRAINT corex_task_occurrences_revision_fk FOREIGN KEY (series_key, immutable_revision) REFERENCES corex_task_series (series_key, immutable_revision) ON DELETE RESTRICT');
            DB::statement("ALTER TABLE corex_task_occurrences ADD CONSTRAINT corex_task_occurrences_occurrence_key_ck CHECK (occurrence_key ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_task_occurrences ADD CONSTRAINT corex_task_occurrences_state_ck CHECK (state IN ('pending','committed','cancelled','retired','unknown'))");
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_task_occurrences_revision_fk BEFORE INSERT ON corex_task_occurrences BEGIN SELECT CASE WHEN EXISTS (SELECT 1 FROM corex_task_series WHERE series_key = NEW.series_key AND immutable_revision = NEW.immutable_revision) THEN 0 ELSE RAISE(ABORT, 'invalid occurrence revision') END; END");
            DB::statement("CREATE TRIGGER corex_task_occurrences_occurrence_key_ck BEFORE INSERT ON corex_task_occurrences BEGIN SELECT CASE WHEN length(NEW.occurrence_key) = 64 AND NEW.occurrence_key NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid occurrence_key') END; END");
            DB::statement("CREATE TRIGGER corex_task_occurrences_state_ck BEFORE INSERT ON corex_task_occurrences BEGIN SELECT CASE WHEN NEW.state IN ('pending','committed','cancelled','retired','unknown') THEN 0 ELSE RAISE(ABORT, 'invalid state') END; END");
        }
    }

    public function down(): void
    {
        foreach (['corex_task_occurrences', 'corex_task_series_state', 'corex_task_series'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Task series mapping requires an approved retention receipt before removal.');
            }
        }

        $this->drop('corex_task_occurrences');
    }
};
