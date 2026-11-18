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
        $this->create('sessions', function (Blueprint $table): void {
            $table->string('id');
            $table->primary(['id'], 'sessions_pk');
            $table->uuid('user_id')->nullable()->index('sessions_user_id_ix');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->bigInteger('last_activity')->index('sessions_last_activity_ix');
        });
    }

    public function down(): void
    {
        $this->drop('sessions');
    }
};
