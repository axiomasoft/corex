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
        $this->create('root_oauth_refresh_tokens', function (Blueprint $table): void {
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
            // Global logout revokes tokens by session and the cascade deletes them by session; the
            // table spans every identity and keeps rotated tokens, so both need this index.
            $table->index(['session_id'], 'root_oauth_refresh_tokens_session_ix');
        });
    }

    public function down(): void
    {
        $this->drop('root_oauth_refresh_tokens');
    }
};
