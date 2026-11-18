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
        $this->create('sys_attribute_key_reservations', function (Blueprint $table): void {
            $table->string('entity', 128);
            $table->string('key', 128);
            $table->uuid('attribute_id');
            $table->unsignedBigInteger('current_revision');
            $table->string('role', 16);
            $table->timestampsTz();
            $table->primary(['entity', 'key'], 'sys_attribute_key_reservations_pk');
            $table->index(['attribute_id', 'current_revision'], 'sys_attribute_key_reservations_attribute_id_current_revision_ix');
        });
    }

    public function down(): void
    {
        $this->drop('sys_attribute_key_reservations');
    }
};
