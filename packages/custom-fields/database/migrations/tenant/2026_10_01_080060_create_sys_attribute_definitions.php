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
            $table->uuid('attribute_id');
            $table->unsignedBigInteger('revision');
            $table->string('entity', 128);
            $table->string('key', 128);
            $table->string('type', 64);
            $table->string('lifecycle', 16);
            $table->boolean('required');
            $table->boolean('searchable');
            $table->jsonb('constraints');
            $table->jsonb('read_keys');
            $table->jsonb('write_keys');
            $table->jsonb('index_policy');
            $table->jsonb('meta');
            $table->char('definition_hash', 64);
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['attribute_id', 'revision'], 'sys_attribute_definitions_pk');
            $table->index(['entity', 'attribute_id', 'revision'], 'sys_attribute_definitions_entity_attribute_id_revision_ix');
        });
    }

    public function down(): void
    {
        $this->drop('sys_attribute_definitions');
    }
};
