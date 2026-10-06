<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();
        $schema = $connection->getSchemaBuilder();

        $subscriptionStatuses = "('active', 'disabled')";
        $deliveryStatuses = "('pending', 'sending', 'retry', 'succeeded', 'dead')";

        $schema->create('sys_api_webhook_attempts', function (Blueprint $table): void {
            $table->string('id', 64);
            $table->primary(['id'], 'sys_api_webhook_attempts_pk');
            $table->string('delivery_id', 64);
            $table->unsignedBigInteger('attempt');
            $table->string('lease_owner', 64);
            $table->timestampTz('started_at', precision: 6);
            $table->timestampTz('finished_at', precision: 6)->nullable();
            $table->string('outcome', 32);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->unique(['delivery_id', 'attempt'], 'sys_api_webhook_attempts_number_uq');
            $table->index(['delivery_id', 'started_at'], 'sys_api_webhook_attempts_delivery_ix');
        });
        $this->addCheck($connection, 'sys_api_webhook_attempts', 'sys_api_webhook_attempts_counts_ck',
            'attempt >= 0 AND (http_status IS NULL OR http_status >= 0) AND (duration_ms IS NULL OR duration_ms >= 0)',
            'NEW.attempt >= 0 AND (NEW.http_status IS NULL OR NEW.http_status >= 0) AND (NEW.duration_ms IS NULL OR NEW.duration_ms >= 0)',
        );

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE sys_api_webhook_attempts RENAME CONSTRAINT sys_api_webhook_attempts_pkey TO sys_api_webhook_attempts_pk');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_api_webhook_attempts');
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
