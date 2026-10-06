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

        $schema->create('sys_api_budget_buckets', function (Blueprint $table): void {
            $table->char('key', 64);
            $table->primary(['key'], 'sys_api_budget_buckets_pk');
            $table->unsignedBigInteger('capacity');
            $table->bigInteger('available');
            $table->unsignedBigInteger('refill_rate');
            $table->timestampTz('last_refill_at', precision: 6);
            $table->timestampTz('last_used_at', precision: 6);
        });

        $this->addCheck($connection, 'sys_api_budget_buckets', 'sys_api_budget_buckets_counts_ck',
            'capacity >= 0 AND refill_rate >= 0',
            'NEW.capacity >= 0 AND NEW.refill_rate >= 0',
        );

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE sys_api_budget_buckets RENAME CONSTRAINT sys_api_budget_buckets_pkey TO sys_api_budget_buckets_pk');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_api_budget_buckets');
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
