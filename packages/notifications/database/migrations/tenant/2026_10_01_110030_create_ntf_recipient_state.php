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
        $this->create('ntf_recipient_state', function (Blueprint $table): void {
            $table->string('kind', 64);
            $table->string('id', 128);
            $table->decimal('preference_revision', 20, 0);
            $table->primary(['kind', 'id'], 'ntf_recipient_state_pk');
        });
    }

    public function down(): void
    {
        if (! app()->environment('testing') && DB::table('ntf_recipient_state')->exists()) {
            throw new RuntimeException('MigrationRollbackDenied: ntf_recipient_state contains durable recipient state.');
        }

        $this->drop('ntf_recipient_state');
    }
};
