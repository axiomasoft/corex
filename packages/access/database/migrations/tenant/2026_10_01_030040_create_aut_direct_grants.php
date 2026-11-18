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
        $this->create('aut_direct_grants', static function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'aut_direct_grants_pk');
            $table->string('principal_type', 32);
            $table->uuid('principal_id');
            $table->string('permission_key', 255);
            $table->timestampTz('expires_at', precision: 6)->nullable();
            $table->timestampTz('revoked_at', precision: 6)->nullable();
            $table->timestampsTz(precision: 6);
            $table->index(['principal_type', 'principal_id', 'permission_key'], 'aut_direct_grants_lookup_ix');
        });
    }

    public function down(): void
    {
        $this->drop('aut_direct_grants');
    }
};
