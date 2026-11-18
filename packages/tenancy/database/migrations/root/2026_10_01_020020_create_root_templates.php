<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->create('root_templates', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'root_templates_pk');
            $table->bigInteger('version')->unique('root_templates_version_uq');
            $table->string('db_name', 63);
            $table->string('status', 16)->default('building');
            $table->string('embedding_model', 64);
            $table->smallInteger('embedding_dim');
            $table->string('git_ref', 64)->nullable();
            $table->string('schema_hash', 64)->nullable();
            $table->timestampTz('built_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });

        DB::statement("ALTER TABLE root_templates ADD CONSTRAINT root_templates_status_ck CHECK (status IN ('building','ready','retired'))");

        DB::statement("COMMENT ON TABLE root_templates IS 'Golden-шаблон = артефакт релиза: CI катает миграции на версионированную шаблонную БД (имя приходит данными — db_name), помечает IS_TEMPLATE; приложение к нему никогда не коннектится'");
        DB::statement("ALTER TABLE root_templates ADD CONSTRAINT root_templates_schema_hash_ck CHECK (schema_hash IS NULL OR schema_hash COLLATE \"C\" ~ '^[0-9a-f]{64}$')");
    }

    public function down(): void
    {
        $this->drop('root_templates');
    }
};
