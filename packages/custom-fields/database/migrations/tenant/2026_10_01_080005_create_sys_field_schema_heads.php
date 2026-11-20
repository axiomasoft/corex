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
        $this->create('sys_field_schema_heads', function (Blueprint $table): void {
            $table->string('account_id', 128);
            $table->string('account_incarnation', 128);
            $table->string('workspace_scope', 140);
            $table->string('entity', 128);
            $table->char('effective_revision', 64);
            $table->char('mapping_digest', 64);
            $table->jsonb('mapping');
            $table->char('manifest_digest', 64);
            $table->uuid('pending_plan')->nullable();
            $table->jsonb('dual_write')->nullable();
            $table->primary(['account_id', 'account_incarnation', 'workspace_scope', 'entity'], 'sys_field_schema_heads_pk');
        });
    }

    public function down(): void
    {
        $this->drop('sys_field_schema_heads');
    }
};
