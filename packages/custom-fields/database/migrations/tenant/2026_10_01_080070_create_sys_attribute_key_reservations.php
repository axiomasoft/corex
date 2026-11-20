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
            $table->string('account_id', 128);
            $table->string('account_incarnation', 128);
            $table->string('workspace_scope', 140);
            $table->string('entity', 128);
            $table->string('machine_key', 128);
            $table->uuid('field_id');
            $table->uuid('operation_id')->nullable();
            $table->primary(['account_id', 'account_incarnation', 'workspace_scope', 'entity', 'machine_key'], 'sys_attribute_key_reservations_pk');
        });
    }

    public function down(): void
    {
        $this->drop('sys_attribute_key_reservations');
    }
};
