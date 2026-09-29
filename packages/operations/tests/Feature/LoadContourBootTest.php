<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\GuardedTenantContextRestorer;
use CoreX\Data\ArrayDataScopeCodec;
use CoreX\Data\DataScope;
use CoreX\Operations\Bulk\Contracts\BulkTransferFactory;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Internal\SqlBulkOperationStore;
use CoreX\Operations\Bulk\Internal\TransferBulkExecutor;
use CoreX\Operations\Bulk\Internal\TransferCapabilityUnavailable;
use CoreX\Operations\Cost\Data\CostOptions;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Tests\TestCase;
use CoreX\Time\FrozenClock;
use CoreX\Transfer\Contracts\ExportRunner;
use CoreX\Transfer\Contracts\TransferRunner;
use DateTimeImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class LoadContourBootTest extends TestCase
{
    public function test_public_surface_keeps_transfer_optional_on_laravel_12_or_13(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);
        $require = $composer['require'];

        self::assertSame(['CoreX\\Operations\\Authorization\\Contracts', 'CoreX\\Operations\\Bulk\\Contracts', 'CoreX\\Operations\\Bulk\\Data', 'CoreX\\Operations\\Cost\\Contracts', 'CoreX\\Operations\\Cost\\Data', 'CoreX\\Operations\\Definition\\ApiOperationValidator', 'CoreX\\Operations\\Definition\\Contracts', 'CoreX\\Operations\\Definition\\Data', 'CoreX\\Operations\\Definition\\Enums\\OperationMode', 'CoreX\\Operations\\Definition\\Operation', 'CoreX\\Operations\\Definition\\OperationRegistry', 'CoreX\\Operations\\Errors\\ApiError', 'CoreX\\Operations\\Errors\\Contracts', 'CoreX\\Operations\\Idempotency\\Contracts', 'CoreX\\Operations\\Idempotency\\Data', 'CoreX\\Operations\\Idempotency\\Enums\\IdempotencyRequirement', 'CoreX\\Operations\\Invocation\\Contracts', 'CoreX\\Operations\\Invocation\\Data', 'CoreX\\Operations\\Invocation\\InvocationMetadata', 'CoreX\\Operations\\Invocation\\OperationDispatcher', 'CoreX\\Operations\\Laravel\\ApiSchemaRegistrar', 'CoreX\\Operations\\Laravel\\Support\\ApiOptions', 'CoreX\\Operations\\OpenApi\\Contracts', 'CoreX\\Operations\\Pagination\\Contracts', 'CoreX\\Operations\\Pagination\\Data', 'CoreX\\Operations\\Pagination\\OrderingRegistry', 'CoreX\\Operations\\Pagination\\SignedCursorCodec', 'CoreX\\Operations\\Testing', 'CoreX\\Operations\\Validation\\Data', 'CoreX\\Operations\\Validation\\JsonCanonical', 'CoreX\\Operations\\Validation\\SchemaBudget', 'CoreX\\Operations\\Validation\\SchemaProfile', 'CoreX\\Operations\\Versioning\\Data'], $composer['extra']['corex']['public-api']);
        self::assertSame(['ext-filter', 'ext-mbstring', 'php', 'axiomasoft/corex', 'illuminate/auth', 'illuminate/console', 'illuminate/contracts', 'illuminate/database', 'illuminate/http', 'illuminate/support', 'illuminate/validation', 'psr/clock', 'symfony/http-kernel'], array_keys($require));
        self::assertSame('^12.0|^13.0', $require['illuminate/http']);
        self::assertSame('^12.0|^13.0', $require['illuminate/support']);
        self::assertSame('^12.0|^13.0', $require['illuminate/validation']);
        self::assertArrayNotHasKey('axiomasoft/corex-transfer', $require);
        self::assertArrayHasKey('axiomasoft/corex-transfer', $composer['suggest']);
        self::assertMatchesRegularExpression('/\A(12|13)\./', Application::VERSION);
        self::assertSame([], $this->foreignInternalImports());

        $gate = json_decode((string) file_get_contents(dirname(__DIR__, 4).'/scripts/package-graph-gate.json'), true);
        $edges = array_values(array_filter(
            $gate['allowlist'],
            static fn (array $entry): bool => $entry['from'] === 'axiomasoft/corex-operations' && $entry['to'] === 'axiomasoft/corex-transfer',
        ));

        self::assertSame([
            'packages/operations/src/Bulk/Data/BulkTransferSession.php',
            'packages/operations/src/Bulk/Internal/TransferBulkExecutor.php',
        ], array_column($edges, 'import_file'));
    }

    public function test_boot_keeps_cost_and_bulk_transfer_disabled(): void
    {
        self::assertFalse($this->app->make(CostOptions::class)->enabled);
        self::assertFalse($this->app->bound(BulkTransferFactory::class));
    }

    public function test_missing_transfer_package_fails_before_submit_and_installed_ports_are_guarded(): void
    {
        self::assertTrue(interface_exists(TransferRunner::class));
        self::assertTrue(interface_exists(ExportRunner::class));
        $this->configure('api_budget_stub');
        $this->configure('api_tenant');
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $this->app['config']->set('corex-api.schema', [
            'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'api_tenant', 'driver' => 'sqlite', 'database' => ':memory:'],
        ]);
        $connection = DB::connection('api_tenant');
        $migration = require dirname(__DIR__, 2).'/database/migrations/tenant/create_sys_api_bulk_operations_table.php';
        $migration->up();
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-26T12:00:00.000000+00:00'));
        $store = new SqlBulkOperationStore(
            connection: $connection,
            scopeCodec: new ArrayDataScopeCodec,
            contexts: new GuardedTenantContextRestorer($contexts),
            clock: $clock,
        );
        $scope = new DataScope(
            accountId: 'account-1',
            workspaceId: null,
            connectionIdentity: $connection->getDriverName().':'.$connection->getDatabaseName(),
            actorId: 'actor-1',
            restoreEpoch: hash('sha256', 'load-contour-restore'),
        );
        $definition = new BulkOperationDefinition(
            id: 'bulk-load-contour',
            submissionId: 'submission-load-contour',
            operationId: 'notes.bulk-import',
            major: 'v1',
            contractHash: hash('sha256', 'bulk-contract'),
            encodedScope: (new ArrayDataScopeCodec)->encode($scope),
            actorId: 'actor-1',
            clientId: 'client-1',
            extensionStamp: [
                'point' => 'api.operation',
                'id' => 'notes.bulk-import',
                'version' => '1',
                'rights' => 'read',
                'restore_epoch' => hash('sha256', 'load-contour-restore'),
                'registryFingerprint' => hash('sha256', 'registry'),
            ],
            inputRef: ['kind' => 'import', 'format' => 'ndjson', 'sha256' => hash('sha256', 'import'), 'length' => 1],
        );

        foreach ([
            new TransferBulkExecutor(null, $store, $clock, scopes: new ArrayDataScopeCodec),
            new TransferBulkExecutor(new \CoreX\Operations\Tests\Fixtures\BulkTransferFactory, $store, $clock, transferInstalled: false, scopes: new ArrayDataScopeCodec),
        ] as $executor) {
            try {
                $executor->submit($definition);
                self::fail('Transfer submit proceeded without the capability.');
            } catch (TransferCapabilityUnavailable $exception) {
                self::assertSame('capability_unavailable', $exception->errorDescriptor()->code);
            }
        }

        self::assertSame(0, $connection->table('sys_api_bulk_operations')->count());
        (new TransferBulkExecutor(new \CoreX\Operations\Tests\Fixtures\BulkTransferFactory, $store, $clock, transferInstalled: true, scopes: new ArrayDataScopeCodec))
            ->submit($definition);
        self::assertSame(1, $connection->table('sys_api_bulk_operations')->count());
    }

    /** @return list<string> */
    private function foreignInternalImports(): array
    {
        $imports = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match('/^use CoreX\\\\(?!Operations\\\\)[^;]*\\\\Internal\\\\/m', $contents) === 1) {
                $imports[] = $file->getPathname();
            }
        }

        sort($imports);

        return $imports;
    }

    private function configure(string $name): void
    {
        $this->app['config']->set('database.connections.'.$name, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge($name);
    }
}
