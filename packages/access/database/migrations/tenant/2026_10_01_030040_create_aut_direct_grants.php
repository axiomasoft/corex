<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aut_direct_grants', static function (Blueprint $table): void {
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
        Schema::getConnection()->statement('ALTER TABLE aut_direct_grants RENAME CONSTRAINT aut_direct_grants_pkey TO aut_direct_grants_pk');
    }

    public function down(): void
    {
        Schema::dropIfExists('aut_direct_grants');
    }
};
