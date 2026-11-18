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
        $this->create('sys_attribute_eav_records', function (Blueprint $table): void {
            $table->string('entity', 128);
            $table->string('record_id', 128);
            $table->string('record_version', 64);
            $table->primary(['entity', 'record_id'], 'sys_attribute_eav_records_pk');
        });
    }

    public function down(): void
    {
        $this->drop('sys_attribute_eav_records');
    }
};
