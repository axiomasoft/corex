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
        $this->create('crm_activity_targets', function (Blueprint $t): void {
            $t->uuid('activity_id');
            $t->string('entity_type', 64);
            $t->string('entity_id', 64);
            $t->addColumn('timestampTz', 'created_at', ['precision' => null])->useCurrent();
            $t->primary(['activity_id', 'entity_type', 'entity_id'], 'crm_activity_targets_pk');

            $t->foreign(['activity_id'], 'crm_activity_targets_activity_id_fk')->references(['id'])->on('crm_activities')->onDelete('cascade')->onUpdate('no action');
        });

        DB::statement('CREATE INDEX "crm_activity_targets_entity_ix" on "crm_activity_targets" ("entity_type", "entity_id")');
    }

    public function down(): void
    {
        throw new RuntimeException('CRM parity rollback requires a verified provenance-preserving backup.');
    }
};
