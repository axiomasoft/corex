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
        $this->create('corex_search_submissions', function (Blueprint $table): void {
            $table->char('lane_key', 64);
            $table->bigInteger('generation');
            $table->uuid('submission_id');
            $table->bigInteger('claim_epoch');
            $table->string('uid', 128);
            $table->string('deployment_id', 128);
            $table->string('binding_id', 128);
            $table->bigInteger('binding_generation');
            $table->char('restore_epoch', 64);
            $table->string('action', 16);
            $table->string('state', 24);
            $table->bigInteger('first_seq')->nullable();
            $table->bigInteger('last_seq')->nullable();
            $table->string('scan_next', 128)->nullable();
            $table->char('batch_digest', 64);
            $table->json('documents');
            $table->string('task_ref', 255)->nullable();
            $table->uuid('provider_ticket')->nullable();
            $table->boolean('control_recorded')->default(false);
            $table->timestampTz('lease_until')->nullable();
            $table->primary(['lane_key', 'generation', 'submission_id'], 'corex_search_submissions_pk');
        });
        $this->db()->statement("create unique index corex_search_submissions_inflight_uq on corex_search_submissions(lane_key,generation) where state in ('planned','sending','accepted','unknown')");
        $this->db()->statement("create index corex_search_submissions_settlement_ix on corex_search_submissions(lane_key,generation,claim_epoch) where control_recorded = false and provider_ticket is not null and state in ('confirmed','failed','not-sent','unknown')");
    }

    public function down(): void
    {
        $this->drop('corex_search_submissions');
    }
};
