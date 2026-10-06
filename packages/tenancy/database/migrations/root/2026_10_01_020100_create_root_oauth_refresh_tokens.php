<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('root_oauth_refresh_tokens', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'root_oauth_refresh_tokens_pk');
            $table->uuid('session_id');
            $table->string('client_id', 64);
            $table->string('token_hash', 64)->unique('root_oauth_refresh_tokens_token_hash_uq');
            $table->timestampTz('expires_at', precision: 6);
            $table->timestampTz('revoked_at', precision: 6)->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->foreign('session_id', 'root_oauth_refresh_tokens_session_id_fk')->references('id')->on('root_idp_sessions')->cascadeOnDelete();
        });

        Schema::getConnection()->statement('ALTER TABLE root_oauth_refresh_tokens RENAME CONSTRAINT root_oauth_refresh_tokens_pkey TO root_oauth_refresh_tokens_pk');
    }

    public function down(): void
    {
        Schema::dropIfExists('root_oauth_refresh_tokens');
    }
};
