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
        $this->create('med_relations', function (Blueprint $table): void {
            $table->id();

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->primary(['id'], 'med_relations_pk');
            }
            $table->uuid('file_id');
            $table->string('entity_handle', 128);
            $table->string('entity_id', 128);
            $table->string('slot', 64);
            $table->timestamps();
            $table->foreign('file_id', 'med_relations_file_id_fk')->references('id')->on('med_files')->restrictOnDelete();
            $table->unique(['file_id', 'entity_handle', 'entity_id', 'slot'], 'med_relations_file_id_entity_handle_entity_id_slot_uq');
        });
    }

    public function down(): void
    {
        $this->drop('med_relations');
    }
};
