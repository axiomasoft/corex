<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->create('ntf_digest_runs', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'ntf_digest_runs_pk');
            $table->string('recipient_kind', 64);
            $table->string('recipient_id', 128);
            $table->string('period', 16);
            $table->timestampTz('period_start');
            $table->timestampTz('period_end');
            $table->unsignedBigInteger('batch_seq');
            $table->char('membership_hash', 64);
            $table->string('state', 16);
            $table->timestampTz('created_at');
            $table->unique(['recipient_kind', 'recipient_id', 'period', 'period_start', 'batch_seq'], 'ntf_digest_runs_recipient_kind_recipient_id_period__27ecc408_uq');
        });
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            $this->drop('ntf_digest_runs');
        }
    }
};
