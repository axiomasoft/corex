<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
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
        $connection = $this->db();

        $deliveryStatuses = "('pending', 'claimed', 'sending', 'retry', 'succeeded', 'dead', 'unknown', 'quarantined')";

        $this->create('sys_api_webhook_deliveries', function (Blueprint $table): void {
            $table->string('id', 64);
            $table->primary(['id'], 'sys_api_webhook_deliveries_pk');
            $table->string('account_id', 128);
            $table->string('account_incarnation', 128);
            $table->string('workspace_scope', 138);
            $table->string('deployment_id', 128);
            $table->string('binding_id', 128);
            $table->unsignedBigInteger('binding_generation');
            $table->char('restore_epoch', 64);
            $table->string('subscription_id', 64);
            $table->unsignedBigInteger('subscription_revision');
            $table->string('event_id', 128);
            $table->string('event_source', 255);
            $table->string('replay_id', 64);
            $table->string('replay_of', 64)->nullable();
            $table->char('effect_key', 64)->unique('sys_api_webhook_deliveries_effect_uq');
            $table->char('scope_seal', 64);
            $table->uuid('claim_token')->nullable();
            $table->uuid('provider_ticket')->nullable();
            $table->string('provider_ref', 255)->nullable();
            $table->string('topic', 191);
            $table->unsignedInteger('topic_version');
            $table->string('major', 32);
            $table->text('endpoint_snapshot');
            $table->json('scope');
            $table->text('body');
            $table->char('body_hash', 64);
            $table->string('status', 16);
            $table->unsignedBigInteger('attempt');
            $table->unsignedBigInteger('replay_count');
            $table->string('lease_owner', 64)->nullable();
            $table->timestampTz('lease_until', precision: 6)->nullable();
            $table->timestampTz('next_attempt_at', precision: 6);
            $table->timestampTz('created_at', precision: 6);
            $table->timestampTz('finished_at', precision: 6)->nullable();
            $table->timestampTz('expires_at', precision: 6);
            $table->text('last_error')->nullable();
            $table->unique(['account_id', 'account_incarnation', 'workspace_scope', 'subscription_id', 'event_source', 'event_id', 'replay_id'], 'sys_api_webhook_deliveries_event_uq');
            $table->index(['account_id', 'account_incarnation', 'workspace_scope', 'status', 'next_attempt_at', 'id'], 'sys_api_webhook_deliveries_claim_ix');
            $table->index(['status', 'lease_until'], 'sys_api_webhook_deliveries_reclaim_ix');
        });
        $this->addCheck($connection, 'sys_api_webhook_deliveries', 'sys_api_webhook_deliveries_status_ck',
            "status IN {$deliveryStatuses}",
            "NEW.status IN {$deliveryStatuses}",
        );
        $this->addCheck($connection, 'sys_api_webhook_deliveries', 'sys_api_webhook_deliveries_counts_ck',
            'subscription_revision >= 0 AND topic_version >= 0 AND attempt >= 0 AND replay_count >= 0',
            'NEW.subscription_revision >= 0 AND NEW.topic_version >= 0 AND NEW.attempt >= 0 AND NEW.replay_count >= 0',
        );
    }

    public function down(): void
    {
        $this->drop('sys_api_webhook_deliveries');
    }

    private function addCheck(
        Connection $connection,
        string $table,
        string $name,
        string $postgresExpression,
        string $sqliteExpression,
    ): void {
        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE "%s" ADD CONSTRAINT "%s" CHECK (%s)',
                $table,
                $name,
                $postgresExpression,
            ));

            return;
        }

        if ($connection->getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $operation) {
                $connection->statement(sprintf(
                    "CREATE TRIGGER \"%s_%s\" BEFORE %s ON \"%s\" WHEN NOT (%s) BEGIN SELECT RAISE(ABORT, '%s'); END",
                    $name,
                    strtolower($operation),
                    $operation,
                    $table,
                    $sqliteExpression,
                    $name,
                ));
            }

            return;
        }

        throw new LogicException(sprintf('Unsupported API schema driver [%s].', $connection->getDriverName()));
    }
};
