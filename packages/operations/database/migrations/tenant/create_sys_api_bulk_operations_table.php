<?php

declare(strict_types=1);

use CoreX\Api\Laravel\ApiSchemaRegistrar;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $connection = app(ApiSchemaRegistrar::class)->tenant();
        $schema = $connection->getSchemaBuilder();

        $schema->create('sys_api_bulk_operations', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('major', 32);
            $table->string('operation_id', 191);
            $table->string('submission_id', 64);
            $table->json('scope');
            $table->string('actor_id', 191);
            $table->string('client_id', 191);
            $table->char('contract_hash', 64);
            $table->json('extension_stamp');
            $table->json('input_ref');
            $table->json('transfer_ref')->nullable();
            $table->string('status', 16);
            $table->string('terminal_status', 16)->nullable();
            $table->unsignedBigInteger('revision');
            $table->string('lease_owner', 64)->nullable();
            $table->timestampTz('lease_until', precision: 6)->nullable();
            $table->timestampTz('cancel_requested_at', precision: 6)->nullable();
            $table->json('checkpoint');
            $table->json('progress');
            $table->json('result_ref')->nullable();
            $table->json('error')->nullable();
            $table->unsignedInteger('attempt');
            $table->timestampTz('next_attempt_at', precision: 6);
            $table->timestampTz('created_at', precision: 6);
            $table->timestampTz('finished_at', precision: 6)->nullable();
            $table->timestampTz('expires_at', precision: 6)->nullable();
            $table->unique(['major', 'operation_id', 'submission_id'], 'sys_api_bulk_operations_submission_unique');
            $table->index(['status', 'next_attempt_at', 'lease_until'], 'sys_api_bulk_operations_claim_idx');
        });

        $statuses = "('pending', 'running', 'cancelling', 'cancelled', 'completed', 'failed', 'expired')";
        $terminalStatuses = "('cancelled', 'completed', 'failed')";

        $this->addCheck($connection, 'sys_api_bulk_operations', 'bulk_operations_status',
            "status IN {$statuses}",
            "NEW.status IN {$statuses}",
        );
        $this->addCheck($connection, 'sys_api_bulk_operations', 'bulk_operations_terminal_status',
            "terminal_status IS NULL OR terminal_status IN {$terminalStatuses}",
            "NEW.terminal_status IS NULL OR NEW.terminal_status IN {$terminalStatuses}",
        );
        $this->addCheck($connection, 'sys_api_bulk_operations', 'bulk_operations_counts',
            'revision >= 0 AND attempt >= 0',
            'NEW.revision >= 0 AND NEW.attempt >= 0',
        );
    }

    public function down(): void
    {
        app(ApiSchemaRegistrar::class)->tenant()->getSchemaBuilder()->dropIfExists('sys_api_bulk_operations');
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
