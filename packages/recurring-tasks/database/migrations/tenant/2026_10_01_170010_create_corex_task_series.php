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
        $this->create('corex_task_series', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'corex_task_series_pk');
            $table->string('series_key', 128);
            $table->string('immutable_revision', 128);
            $table->char('definition_hash', 64);
            $table->json('definition');
            $table->json('template');
            $table->json('actor');
            $table->char('context_hash', 64);
            $table->char('restore_epoch', 64);
            $table->unique(['series_key', 'immutable_revision'], 'corex_task_series_series_key_immutable_revision_uq');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE corex_task_series ADD CONSTRAINT corex_task_series_definition_hash_ck CHECK (definition_hash ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_task_series ADD CONSTRAINT corex_task_series_context_hash_ck CHECK (context_hash ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_task_series ADD CONSTRAINT corex_task_series_restore_epoch_ck CHECK (restore_epoch ~ '^[a-f0-9]{64}$')");
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_task_series_definition_hash_ck BEFORE INSERT ON corex_task_series BEGIN SELECT CASE WHEN length(NEW.definition_hash) = 64 AND NEW.definition_hash NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid definition_hash') END; END");
            DB::statement("CREATE TRIGGER corex_task_series_context_hash_ck BEFORE INSERT ON corex_task_series BEGIN SELECT CASE WHEN length(NEW.context_hash) = 64 AND NEW.context_hash NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid context_hash') END; END");
            DB::statement("CREATE TRIGGER corex_task_series_restore_epoch_ck BEFORE INSERT ON corex_task_series BEGIN SELECT CASE WHEN length(NEW.restore_epoch) = 64 AND NEW.restore_epoch NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid restore_epoch') END; END");
        }
    }

    public function down(): void
    {
        foreach (['corex_task_occurrences', 'corex_task_series_state', 'corex_task_series'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Task series mapping requires an approved retention receipt before removal.');
            }
        }

        $this->drop('corex_task_series');
    }
};
