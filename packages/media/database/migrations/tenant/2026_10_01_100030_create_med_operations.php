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
        $this->create('med_operations', function (Blueprint $table): void {
            $table->id();

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->primary(['id'], 'med_operations_pk');
            }
            $table->uuid('file_id');
            $table->string('operation_key', 128)->unique('med_operations_operation_key_uq');
            $table->string('kind', 32);
            $table->string('state', 16);
            $table->string('profile_key', 128)->nullable();
            $table->char('profile_digest', 64)->nullable();
            $table->unsignedBigInteger('source_version')->nullable();
            $table->unsignedBigInteger('claim_epoch')->default(0);
            $table->unsignedBigInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->timestamps();
            $table->foreign('file_id', 'med_operations_file_id_fk')->references('id')->on('med_files')->restrictOnDelete();
            $table->index(['state', 'next_attempt_at'], 'med_operations_state_next_attempt_at_ix');
        });
    }

    public function down(): void
    {
        $this->drop('med_operations');
    }
};
