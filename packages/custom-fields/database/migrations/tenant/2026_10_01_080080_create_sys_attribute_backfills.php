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
        $this->create('sys_attribute_backfills', function (Blueprint $table): void {
            $table->uuid('operation_id');
            $table->string('partition_id', 320);
            $table->string('kind', 16);
            $table->string('record_id', 128)->nullable();
            $table->char('seen_revision', 64)->nullable();
            $table->string('cursor_record_id', 128)->nullable();
            $table->string('upper_record_id', 128)->nullable();
            $table->boolean('exhausted')->default(false);
            $table->string('error_code', 128)->nullable();
            $table->primary(['operation_id', 'partition_id'], 'sys_attribute_backfills_pk');
            $table->index(['operation_id', 'kind', 'record_id'], 'sys_attribute_backfills_pending_ix');
        });
    }

    public function down(): void
    {
        $this->drop('sys_attribute_backfills');
    }
};
