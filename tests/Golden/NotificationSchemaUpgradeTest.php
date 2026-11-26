<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden;

use CoreX\Tests\Golden\Support\SchemaEnvironment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class NotificationSchemaUpgradeTest extends GoldenTestCase
{
    use SchemaEnvironment;

    /** @return array<string, array{bool, bool}> */
    public static function interruptionPoints(): array
    {
        return [
            'during DDL' => [false, false],
            'after DDL, before recording the migration' => [true, false],
            'column left behind by an earlier nontransactional attempt' => [false, true],
        ];
    }

    #[DataProvider('interruptionPoints')]
    public function test_an_interrupted_retention_upgrade_preserves_rows_and_can_be_retried_on_sqlite(bool $beforeJournal, bool $columnAlreadyAdded): void
    {
        $set = $this->schemaRegistry()->get('axiomasoft/corex-notifications:tenant');
        $this->useSchemaSelection([$set]);
        $connection = DB::connection('schema_golden');
        self::assertSame(':memory:', $connection->getDatabaseName());

        // Install the six journalled files of the previous notification schema.
        $baseline = glob($set->path.'/2026_10_01_1100[1-6]0_*.php');
        self::assertIsArray($baseline);
        self::assertCount(6, $baseline);
        self::assertSame(0, Artisan::call('migrate', [
            '--database' => 'schema_golden', '--path' => $baseline, '--realpath' => true, '--force' => true,
        ]));
        $id = '00000000-0000-7000-8000-000000000001';
        $notifications = $connection->table('ntf_notifications');
        $notifications->insert([
            'id' => $id, 'account_id' => 'account', 'account_incarnation' => 'first', 'workspace_scope' => 'ws',
            'recipient_kind' => 'user', 'recipient_id' => 'member', 'source' => 'sales', 'event_id' => 'sold',
            'request_digest' => str_repeat('1', 64), 'event_type' => 'sale', 'template_action' => 'receipt',
            'payload' => '{"sale":5}', 'read_at' => '2020-01-01 12:00:00', 'revision' => 3,
        ]);
        $before = $notifications->where('id', $id)->firstOrFail();

        if ($columnAlreadyAdded) {
            $connection->getSchemaBuilder()->table('ntf_notifications', static function (Blueprint $table): void {
                $table->timestampTz('pruned_at')->nullable();
            });
        }

        $interrupt = true;
        $connection->beforeExecuting(static function (string $sql, array $bindings) use (&$interrupt, $beforeJournal): void {
            $atCheckpoint = $beforeJournal
                ? str_starts_with(strtolower($sql), 'insert into "migrations"') && in_array('2026_10_01_110070_add_ntf_notifications_retention', $bindings, true)
                : str_starts_with(strtolower($sql), 'create index') && str_contains($sql, 'ntf_notifications_retention_ix');

            if ($interrupt && $atCheckpoint) {
                $interrupt = false;

                throw new RuntimeException('Simulated migration interruption.');
            }
        });
        $migrator = $this->schemaMigrator();

        try {
            $migrator->migrateSets([$set->id()], 'schema_golden');
            self::fail('The retention migration did not reach the interruption point.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated migration interruption.', $exception->getMessage());
        }

        self::assertSame(6, $connection->table('migrations')->count());
        $applied = $migrator->migrateSets([$set->id()], 'schema_golden');
        self::assertContains('2026_10_01_110070_add_ntf_notifications_retention', $applied);
        self::assertContains('2026_10_01_110995_add_lookup_indexes', $applied);
        self::assertSame([...get_object_vars($before), 'pruned_at' => null], get_object_vars($notifications->firstOrFail()));
        foreach (['ntf_notifications_retention_ix', 'ntf_notifications_inbox_ix'] as $name) {
            $definition = $connection->scalar("SELECT sql FROM sqlite_master WHERE type = 'index' AND name = ?", [$name]);
            self::assertIsString($definition);
            self::assertStringContainsString('pruned_at is null', strtolower($definition));
        }
        self::assertTrue($connection->getSchemaBuilder()->hasIndex('ntf_digest_items', 'ntf_digest_items_run_id_ix'));
        self::assertSame([], $migrator->migrateSets([$set->id()], 'schema_golden'));
    }
}
