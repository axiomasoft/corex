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

        $schema->create('sys_api_budget_reservations', function (Blueprint $table): void {
            $table->string('id', 64);
            $table->primary(['id'], 'sys_api_budget_reservations_pk');
            $table->json('keys');
            $table->unsignedBigInteger('estimate');
            $table->unsignedBigInteger('charged');
            $table->char('recipe_hash', 64);
            $table->string('state', 16);
            $table->timestampTz('created_at', precision: 6);
            $table->timestampTz('expires_at', precision: 6);
            $table->index(['state', 'expires_at'], 'sys_api_budget_reservations_retention_ix');
        });
        $this->addCheck($connection, 'sys_api_budget_reservations', 'sys_api_budget_reservations_counts_ck',
            'estimate >= 0 AND charged >= 0',
            'NEW.estimate >= 0 AND NEW.charged >= 0',
        );
        $this->addCheck($connection, 'sys_api_budget_reservations', 'sys_api_budget_reservations_state_ck',
            "state IN ('held', 'settled', 'rejected', 'abandoned')",
            "NEW.state IN ('held', 'settled', 'rejected', 'abandoned')",
        );
        $this->addCheck($connection, 'sys_api_budget_reservations', 'sys_api_budget_reservations_keys_ck',
            'jsonb_array_length(keys::jsonb) = 4',
            'json_array_length(NEW.keys) = 4',
        );

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE sys_api_budget_reservations RENAME CONSTRAINT sys_api_budget_reservations_pkey TO sys_api_budget_reservations_pk');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_api_budget_reservations');
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
