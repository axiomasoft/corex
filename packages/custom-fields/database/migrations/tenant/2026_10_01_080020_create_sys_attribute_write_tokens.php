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
        $this->create('sys_attribute_write_tokens', function (Blueprint $table): void {
            $table->string('operation_key', 128);
            $table->primary(['operation_key'], 'sys_attribute_write_tokens_pk');
            $table->string('entity', 128);
            $table->string('record_id', 128);
            $table->string('payload_hash', 64);
            $table->string('record_version', 64);
        });
    }

    public function down(): void
    {
        $this->drop('sys_attribute_write_tokens');
    }
};
