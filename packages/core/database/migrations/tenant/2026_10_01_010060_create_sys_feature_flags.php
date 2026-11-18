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
        $this->create('sys_feature_flags', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'sys_feature_flags_pk');
            $table->string('key', 128);
            $table->unique(['key'], 'sys_feature_flags_key_uq');
            $table->string('module', 190)->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });
    }

    public function down(): void
    {
        $this->drop('sys_feature_flags');
    }
};
