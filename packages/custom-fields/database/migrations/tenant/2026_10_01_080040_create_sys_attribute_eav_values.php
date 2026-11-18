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
        $this->create('sys_attribute_eav_values', function (Blueprint $table): void {
            $table->string('entity', 128);
            $table->string('record_id', 128);
            $table->string('field', 128);
            $table->jsonb('value');
            $table->primary(['entity', 'record_id', 'field'], 'sys_attribute_eav_values_pk');
        });
    }

    public function down(): void
    {
        $this->drop('sys_attribute_eav_values');
    }
};
