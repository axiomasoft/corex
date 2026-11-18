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
        $this->create('med_files', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'med_files_pk');
            $table->string('request_key', 128)->unique('med_files_request_key_uq');
            $table->char('request_digest', 64);
            $table->string('state', 16);
            $table->string('account_id', 128);
            $table->string('blob_id', 128)->nullable();
            $table->string('mime_type', 255);
            $table->string('kind', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('uploaded_actor_kind', 64);
            $table->string('uploaded_actor_id', 128)->nullable();
            $table->string('workspace_scope_key', 128)->nullable();
            $table->unsignedBigInteger('version');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        $this->drop('med_files');
    }
};
