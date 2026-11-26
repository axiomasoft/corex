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
        $this->create('corex_task_series_state', function (Blueprint $table): void {
            $table->string('series_key', 128);
            $table->primary(['series_key'], 'corex_task_series_state_pk');
            $table->string('active_revision', 128);
            $table->boolean('retired')->default(false);
            $table->string('complete_through', 128)->nullable();
            $table->json('cursor')->nullable();
            $table->char('restore_epoch', 64);
            $table->decimal('generation', total: 20, places: 0)->default(0);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE corex_task_series_state ADD CONSTRAINT corex_task_series_state_revision_fk FOREIGN KEY (series_key, active_revision) REFERENCES corex_task_series (series_key, immutable_revision) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE corex_task_series_state ADD CONSTRAINT corex_task_series_state_retired_ck CHECK (retired IN (TRUE, FALSE))');
            DB::statement("ALTER TABLE corex_task_series_state ADD CONSTRAINT corex_task_series_state_restore_epoch_ck CHECK (restore_epoch ~ '^[a-f0-9]{64}$')");
            DB::statement('ALTER TABLE corex_task_series_state ADD CONSTRAINT corex_task_series_state_generation_ck CHECK (generation BETWEEN 0 AND 18446744073709551615)');
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_task_series_state_revision_fk BEFORE INSERT ON corex_task_series_state BEGIN SELECT CASE WHEN EXISTS (SELECT 1 FROM corex_task_series WHERE series_key = NEW.series_key AND immutable_revision = NEW.active_revision) THEN 0 ELSE RAISE(ABORT, 'invalid active revision') END; END");
            DB::statement("CREATE TRIGGER corex_task_series_state_retired_ck BEFORE INSERT ON corex_task_series_state BEGIN SELECT CASE WHEN NEW.retired IN (0, 1) THEN 0 ELSE RAISE(ABORT, 'invalid retired') END; END");
            DB::statement("CREATE TRIGGER corex_task_series_state_restore_epoch_ck BEFORE INSERT ON corex_task_series_state BEGIN SELECT CASE WHEN length(NEW.restore_epoch) = 64 AND NEW.restore_epoch NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid restore_epoch') END; END");
            DB::statement("CREATE TRIGGER corex_task_series_state_generation_ck BEFORE INSERT ON corex_task_series_state BEGIN SELECT CASE WHEN length(NEW.generation) <= 20 AND NEW.generation NOT GLOB '*[^0-9]*' AND NEW.generation <= '18446744073709551615' THEN 0 ELSE RAISE(ABORT, 'invalid generation') END; END");
        }
    }

    public function down(): void
    {
        foreach (['corex_task_occurrences', 'corex_task_series_state', 'corex_task_series'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Task series mapping requires an approved retention receipt before removal.');
            }
        }

        $this->drop('corex_task_series_state');
    }
};
