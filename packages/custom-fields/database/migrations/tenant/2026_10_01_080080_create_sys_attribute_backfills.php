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
            $table->uuid('id');
            $table->primary(['id'], 'sys_attribute_backfills_pk');
            $table->uuid('attribute_id');
            $table->unsignedBigInteger('from_revision');
            $table->unsignedBigInteger('to_revision');
            $table->string('entity', 128);
            $table->string('source_key', 128);
            $table->string('target_key', 128);
            $table->string('status', 16);
            $table->string('cursor_record_id', 128)->nullable();
            $table->unsignedBigInteger('attempted')->default(0);
            $table->unsignedBigInteger('changed')->default(0);
            $table->unsignedBigInteger('failed')->default(0);
            $table->char('source_digest', 64)->nullable();
            $table->char('target_digest', 64)->nullable();
            $table->text('error')->nullable();
            $table->timestampsTz();
            $table->index(['attribute_id', 'to_revision'], 'sys_attribute_backfills_attribute_id_to_revision_ix');
        });
    }

    public function down(): void
    {
        $this->drop('sys_attribute_backfills');
    }
};
