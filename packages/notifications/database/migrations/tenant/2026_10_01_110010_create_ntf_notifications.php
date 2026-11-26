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
        $this->create('ntf_notifications', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'ntf_notifications_pk');
            $table->string('account_id', 128);
            $table->string('account_incarnation', 128);
            $table->string('workspace_scope', 138);
            $table->string('recipient_kind', 64);
            $table->string('recipient_id', 128);
            $table->string('source', 128);
            $table->string('event_id', 128);
            $table->char('request_digest', 64);
            $table->string('event_type', 128);
            $table->string('template_action', 128);
            $table->json('payload');
            $table->json('related')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('dismissed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->decimal('revision', 20, 0);
            $table->timestampsTz();
            $table->unique(['account_id', 'account_incarnation', 'workspace_scope', 'recipient_kind', 'recipient_id', 'source', 'event_id', 'template_action'], 'ntf_notifications_intent_uq');
            $table->index(['account_id', 'account_incarnation', 'workspace_scope', 'recipient_kind', 'recipient_id', 'read_at', 'id'], 'ntf_notifications_scan_ix');
        });
    }

    public function down(): void
    {
        if (! app()->environment('testing') && DB::table('ntf_notifications')->exists()) {
            throw new RuntimeException('MigrationRollbackDenied: ntf_notifications contains durable notification state.');
        }

        $this->drop('ntf_notifications');
    }
};
