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
        $this->create('corex_rate_current', function (Blueprint $table): void {
            $table->string('base', 3);
            $table->string('quote', 3);
            $table->string('source', 128);
            $table->char('snapshot_id', 26);
            $table->decimal('generation', total: 20, places: 0)->default(0);
            $table->primary(['base', 'quote', 'source'], 'corex_rate_current_pk');
            $table->index(['snapshot_id'], 'corex_rate_current_snapshot_id_ix');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE corex_rate_current ADD CONSTRAINT corex_rate_current_snapshot_fk FOREIGN KEY (snapshot_id) REFERENCES corex_rate_snapshots (id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE corex_rate_current ADD CONSTRAINT corex_rate_current_generation_ck CHECK (generation BETWEEN 0 AND 18446744073709551615)');
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_rate_current_snapshot_fk BEFORE INSERT ON corex_rate_current BEGIN SELECT CASE WHEN EXISTS (SELECT 1 FROM corex_rate_snapshots WHERE id = NEW.snapshot_id) THEN 0 ELSE RAISE(ABORT, 'invalid snapshot_id') END; END");
        }
    }

    public function down(): void
    {
        foreach (['corex_rate_current', 'corex_rate_batches', 'corex_rate_snapshots'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Rate snapshot tables require an approved retention receipt before removal.');
            }
        }

        $this->drop('corex_rate_current');
    }
};
