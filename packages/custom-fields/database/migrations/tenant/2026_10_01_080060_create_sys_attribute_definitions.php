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
        $this->create('sys_attribute_definitions', function (Blueprint $table): void {
            $table->string('account_id', 128);
            $table->string('account_incarnation', 128);
            $table->string('workspace_scope', 140);
            $table->string('entity', 128);
            $table->uuid('field_id');
            $table->bigInteger('revision');
            $table->bigInteger('storage_revision');
            $table->string('owner', 191);
            $table->string('provenance', 16);
            $table->jsonb('definition');
            $table->char('definition_digest', 64);
            $table->primary(['account_id', 'account_incarnation', 'workspace_scope', 'entity', 'field_id', 'revision'], 'sys_attribute_definitions_pk');
        });
    }

    public function down(): void
    {
        $this->drop('sys_attribute_definitions');
    }
};
