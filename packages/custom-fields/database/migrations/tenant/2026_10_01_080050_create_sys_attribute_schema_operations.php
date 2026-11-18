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
        $this->create('sys_attribute_schema_operations', function (Blueprint $table): void {
            $table->string('id', 64);
            $table->primary(['id'], 'sys_attribute_schema_operations_pk');
            $table->string('operation_key', 128)->unique('sys_attribute_schema_operations_operation_key_uq');
            $table->string('payload_hash', 64);
            $table->string('entity', 128);
            $table->string('schema_hash', 64);
            $table->string('approval_digest', 64);
            $table->string('restore_epoch', 64);
            $table->jsonb('definition');
            $table->string('status', 16);
            $table->unsignedBigInteger('attempts')->default(0);
            $table->string('error_code', 128)->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        $this->drop('sys_attribute_schema_operations');
    }
};
