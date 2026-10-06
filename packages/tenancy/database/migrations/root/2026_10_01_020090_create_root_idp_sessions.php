<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('root_idp_sessions', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'root_idp_sessions_pk');
            $table->uuid('identity_id');
            $table->string('token_hash', 64)->unique('root_idp_sessions_token_hash_uq');
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestampTz('expires_at', precision: 6);
            $table->timestampTz('revoked_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->foreign('identity_id', 'root_idp_sessions_identity_id_fk')->references('id')->on('root_identities')->cascadeOnDelete();
        });

        Schema::getConnection()->statement('ALTER TABLE root_idp_sessions RENAME CONSTRAINT root_idp_sessions_pkey TO root_idp_sessions_pk');

        DB::statement('CREATE INDEX root_idp_sessions_identity_ix ON root_idp_sessions (identity_id) WHERE revoked_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('root_idp_sessions');
    }
};
