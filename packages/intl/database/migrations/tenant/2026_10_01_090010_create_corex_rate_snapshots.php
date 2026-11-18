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
        $this->create('corex_rate_snapshots', function (Blueprint $table): void {
            $table->char('id', 26);
            $table->primary(['id'], 'corex_rate_snapshots_pk');
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
            $table->unique(['source', 'publication_id', 'publication_revision', 'base', 'quote', 'valid_on'], 'corex_rate_snapshots_publication_uq');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE corex_rate_snapshots ADD CONSTRAINT corex_rate_snapshots_payload_hash_ck CHECK (payload_hash ~ '^[a-f0-9]{64}$')");
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_rate_snapshots_payload_hash_ck BEFORE INSERT ON corex_rate_snapshots BEGIN SELECT CASE WHEN length(NEW.payload_hash) = 64 AND NEW.payload_hash NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid payload_hash') END; END");
        }
    }

    public function down(): void
    {
        foreach (['corex_rate_current', 'corex_rate_batches', 'corex_rate_snapshots'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Rate snapshot tables require an approved retention receipt before removal.');
            }
        }

        $this->drop('corex_rate_snapshots');
    }
};
