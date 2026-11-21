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
        $this->create('corex_transfer_steps', function (Blueprint $table): void {
            $table->uuid('job_id');
            $table->char('step_key', 64);
            $table->char('digest', 64);
            $table->char('batch_hash', 64)->nullable();
            $table->jsonb('receipt')->nullable();
            $table->primary(['job_id', 'step_key'], 'corex_transfer_steps_pk');
            $table->foreign('job_id', 'corex_transfer_steps_job_fk')->references('id')->on('corex_transfer_jobs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if ($this->db()->table('corex_transfer_steps')->exists()) {
            throw new LogicException('Transfer step evidence requires approved retention before removal.');
        }

        $this->drop('corex_transfer_steps');
    }
};
