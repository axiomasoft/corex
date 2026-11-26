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
        $this->create('root_clusters', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'root_clusters_pk');
            $table->string('name', 64)->unique('root_clusters_name_uq');
            $table->string('region', 32)->default('ru-central');
            $table->string('status', 16)->default('active');
            $table->string('pg_host_writer', 255);
            $table->string('pg_host_reader', 255)->nullable();
            $table->integer('pg_port')->default(5432);
            $table->string('pgbouncer_host', 255);
            $table->integer('pgbouncer_port')->default(6432);
            $table->string('app_db_user', 64);
            $table->string('tei_endpoint', 255)->nullable();
            $table->string('backup_repo', 255)->nullable();
            $table->bigInteger('db_count')->default(0);
            $table->integer('db_soft_limit')->default(700);
            $table->integer('pending_pool_size')->default(20);
            $table->bigInteger('template_version')->default(0);
            $table->jsonb('meta')->default('{}');
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });

        DB::statement("ALTER TABLE root_clusters ADD CONSTRAINT root_clusters_status_ck CHECK (status IN ('provisioning','active','draining','readonly','retired'))");
    }

    public function down(): void
    {
        $this->drop('root_clusters');
    }
};
