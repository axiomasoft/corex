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
        $this->create('root_oauth_clients', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'root_oauth_clients_pk');
            $table->string('client_id', 64)->unique('root_oauth_clients_client_id_uq');
            $table->string('secret_hash', 255)->nullable();
            $table->string('name', 128);
            $table->string('product', 32)->nullable();
            $table->jsonb('redirect_patterns');
            $table->boolean('is_first_party')->default(true);
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
            $table->timestampTz('deleted_at', precision: 6)->nullable();
        });

        DB::statement("COMMENT ON COLUMN root_oauth_clients.redirect_patterns IS 'wildcard покрывает платформенные поддомены; custom-домены валидируются точным матчем по root_domains(verified)'");
    }

    public function down(): void
    {
        $this->drop('root_oauth_clients');
    }
};
