<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Packman\Schema\Laravel\PackmanMigration;

// Durable subscriber positions use the transport's monotonic message cursor.
return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->create('sys_pubsub_cursors', function (Blueprint $table): void {
            $table->string('channel', 190);
            $table->string('subscriber', 190);
            $table->unsignedBigInteger('position');
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->primary(['channel', 'subscriber'], 'sys_pubsub_cursors_pk');
        });
    }

    public function down(): void
    {
        $this->drop('sys_pubsub_cursors');
    }
};
