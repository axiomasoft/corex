<?php

declare(strict_types=1);

use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $connection = app(ApiSchemaRegistrar::class)->tenant();
        $schema = $connection->getSchemaBuilder();

        $schema->create('sys_api_webhook_subscriptions', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->unsignedBigInteger('revision');
            $table->string('major', 32);
            $table->string('topic', 191);
            $table->unsignedInteger('topic_version');
            $table->text('endpoint_url');
            $table->string('status', 16);
            $table->string('max_pii_class', 32);
            $table->json('key_refs');
            $table->timestampTz('created_at', precision: 6);
            $table->timestampTz('updated_at', precision: 6);
            $table->index(['status', 'topic', 'major'], 'sys_api_webhook_subscriptions_lookup_idx');
        });

        $schema->create('sys_api_webhook_deliveries', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('subscription_id', 64);
            $table->unsignedBigInteger('subscription_revision');
            $table->string('event_id', 64);
            $table->string('topic', 191);
            $table->unsignedInteger('topic_version');
            $table->string('major', 32);
            $table->text('endpoint_snapshot');
            $table->json('scope');
            $table->text('body');
            $table->char('body_hash', 64);
            $table->string('status', 16);
            $table->unsignedInteger('attempt');
            $table->unsignedInteger('replay_count');
            $table->string('lease_owner', 64)->nullable();
            $table->timestampTz('lease_until', precision: 6)->nullable();
            $table->timestampTz('next_attempt_at', precision: 6);
            $table->timestampTz('created_at', precision: 6);
            $table->timestampTz('finished_at', precision: 6)->nullable();
            $table->timestampTz('expires_at', precision: 6);
            $table->text('last_error')->nullable();
            $table->unique(['subscription_id', 'event_id'], 'sys_api_webhook_deliveries_event_unique');
            $table->index(['status', 'next_attempt_at', 'lease_until'], 'sys_api_webhook_deliveries_claim_idx');
        });

        $schema->create('sys_api_webhook_attempts', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('delivery_id', 64);
            $table->unsignedInteger('attempt');
            $table->string('lease_owner', 64);
            $table->timestampTz('started_at', precision: 6);
            $table->timestampTz('finished_at', precision: 6)->nullable();
            $table->string('outcome', 32);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->unique(['delivery_id', 'attempt'], 'sys_api_webhook_attempts_number_unique');
            $table->index(['delivery_id', 'started_at'], 'sys_api_webhook_attempts_delivery_idx');
        });

        $subscriptionStatuses = "('active', 'disabled')";
        $deliveryStatuses = "('pending', 'sending', 'retry', 'succeeded', 'dead')";

        $this->addCheck($connection, 'sys_api_webhook_subscriptions', 'webhook_subscriptions_status',
            "status IN {$subscriptionStatuses}",
            "NEW.status IN {$subscriptionStatuses}",
        );
        $this->addCheck($connection, 'sys_api_webhook_subscriptions', 'webhook_subscriptions_counts',
            'revision >= 0 AND topic_version >= 0',
            'NEW.revision >= 0 AND NEW.topic_version >= 0',
        );
        $this->addCheck($connection, 'sys_api_webhook_deliveries', 'webhook_deliveries_status',
            "status IN {$deliveryStatuses}",
            "NEW.status IN {$deliveryStatuses}",
        );
        $this->addCheck($connection, 'sys_api_webhook_deliveries', 'webhook_deliveries_counts',
            'subscription_revision >= 0 AND topic_version >= 0 AND attempt >= 0 AND replay_count >= 0',
            'NEW.subscription_revision >= 0 AND NEW.topic_version >= 0 AND NEW.attempt >= 0 AND NEW.replay_count >= 0',
        );
        $this->addCheck($connection, 'sys_api_webhook_attempts', 'webhook_attempts_counts',
            'attempt >= 0 AND (http_status IS NULL OR http_status >= 0) AND (duration_ms IS NULL OR duration_ms >= 0)',
            'NEW.attempt >= 0 AND (NEW.http_status IS NULL OR NEW.http_status >= 0) AND (NEW.duration_ms IS NULL OR NEW.duration_ms >= 0)',
        );
    }

    public function down(): void
    {
        $schema = app(ApiSchemaRegistrar::class)->tenant()->getSchemaBuilder();

        $schema->dropIfExists('sys_api_webhook_attempts');
        $schema->dropIfExists('sys_api_webhook_deliveries');
        $schema->dropIfExists('sys_api_webhook_subscriptions');
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
