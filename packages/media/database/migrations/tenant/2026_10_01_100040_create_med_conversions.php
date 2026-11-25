<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->create('med_conversions', function (Blueprint $table): void {
            $table->id();

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->primary(['id'], 'med_conversions_pk');
            }
            $table->uuid('file_id');
            $table->string('account_id', 128);
            $table->string('account_incarnation', 128);
            $table->string('workspace_scope', 138);
            $table->string('profile_key', 128);
            $table->char('profile_digest', 64);
            $table->unsignedBigInteger('source_version');
            $table->string('state', 16);
            $table->string('blob_id', 128)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->string('mime_type', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
            $table->foreign('file_id', 'med_conversions_file_id_fk')->references('id')->on('med_files')->restrictOnDelete();
            $table->unique([
                'account_id', 'account_incarnation', 'workspace_scope', 'file_id', 'source_version', 'profile_key', 'profile_digest',
            ], 'med_conversions_scope_file_profile_uq');
            // Purge completion releases every conversion of a file by file id alone.
            $table->index(['file_id'], 'med_conversions_file_ix');
        });
    }

    public function down(): void
    {
        $this->drop('med_conversions');
    }
};
