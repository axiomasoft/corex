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

        $subscriptionStatuses = "('active', 'disabled')";
        $deliveryStatuses = "('pending', 'sending', 'retry', 'succeeded', 'dead')";

        $this->create('sys_api_webhook_subscriptions', function (Blueprint $table): void {
            $table->string('id', 64);
            $table->primary(['id'], 'sys_api_webhook_subscriptions_pk');
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
            $table->index(['status', 'topic', 'major'], 'sys_api_webhook_subscriptions_lookup_ix');
        });

        $this->addCheck($connection, 'sys_api_webhook_subscriptions', 'sys_api_webhook_subscriptions_status_ck',
            "status IN {$subscriptionStatuses}",
            "NEW.status IN {$subscriptionStatuses}",
        );
        $this->addCheck($connection, 'sys_api_webhook_subscriptions', 'sys_api_webhook_subscriptions_counts_ck',
            'revision >= 0 AND topic_version >= 0',
            'NEW.revision >= 0 AND NEW.topic_version >= 0',
        );
    }

    public function down(): void
    {
        $this->drop('sys_api_webhook_subscriptions');
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
