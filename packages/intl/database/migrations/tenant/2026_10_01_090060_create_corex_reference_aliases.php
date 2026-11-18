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
        $this->create('corex_reference_aliases', function (Blueprint $table): void {
            $table->string('snapshot_id', 128);
            $table->string('namespace', 128);
            $table->string('source_id', 255);
            $table->string('locale', 128);
            $table->string('alias', 255);
            $table->string('normalized_alias', 255);
            $table->primary(['snapshot_id', 'namespace', 'source_id', 'locale', 'alias'], 'corex_reference_aliases_pk');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE corex_reference_aliases ADD CONSTRAINT corex_reference_aliases_record_fk FOREIGN KEY (snapshot_id, namespace, source_id) REFERENCES corex_reference_records (snapshot_id, namespace, source_id) ON DELETE RESTRICT');
        }
    }

    public function down(): void
    {
        foreach (['corex_reference_aliases', 'corex_reference_records', 'corex_reference_current', 'corex_reference_datasets'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Reference snapshot tables require an approved retention receipt before removal.');
            }
        }

        $this->drop('corex_reference_aliases');
    }
};
