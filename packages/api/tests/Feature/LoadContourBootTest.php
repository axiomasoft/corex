<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Feature;

use CoreX\Api\ApiServiceProvider;
use CoreX\Api\Contracts\BulkTransferFactory;
use CoreX\Api\Contracts\WebhookSigner;
use CoreX\Api\Data\BulkOperationDefinition;
use CoreX\Api\Data\CostOptions;
use CoreX\Api\Data\WebhookOptions;
use CoreX\Api\Internal\Bulk\SqlBulkOperationStore;
use CoreX\Api\Internal\Bulk\TransferBulkExecutor;
use CoreX\Api\Internal\Bulk\TransferCapabilityUnavailable;
use CoreX\Api\Laravel\ApiSchemaRegistrar;
use CoreX\Api\Tests\Fixtures\BulkTransferFactory as BulkTransferFactoryFixture;
use CoreX\Api\Tests\Support\ContextFixture;
use CoreX\Api\Tests\TestCase;
use CoreX\Support\Data\ArrayDataScopeCodec;
use CoreX\Support\Data\DataScope;
use CoreX\Support\InvalidConfiguration;
use CoreX\Support\Time\FrozenClock;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\GuardedTenantContextRestorer;
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

        self::assertSame(['CoreX\\Api\\Contracts', 'CoreX\\Api\\Data'], $composer['extra']['corex']['public-api']);
        self::assertSame([
            'php',
            'corex/core',
            'corex/modules',
            'illuminate/http',
            'illuminate/support',
            'illuminate/validation',
        ], array_keys($require));
        self::assertSame('^12.0|^13.0', $require['illuminate/http']);
        self::assertSame('^12.0|^13.0', $require['illuminate/support']);
        self::assertSame('^12.0|^13.0', $require['illuminate/validation']);
        self::assertArrayNotHasKey('corex/transfer', $require);
        self::assertArrayHasKey('corex/transfer', $composer['suggest']);
        self::assertMatchesRegularExpression('/\A(12|13)\./', Application::VERSION);
        self::assertSame([], $this->foreignInternalImports());

        $gate = json_decode((string) file_get_contents(dirname(__DIR__, 4).'/scripts/package-graph-gate.json'), true);
        $edges = array_values(array_filter(
            $gate['allowlist'],
            static fn (array $entry): bool => $entry['from'] === 'corex/api' && $entry['to'] === 'corex/transfer',
        ));

        self::assertSame([
            'packages/api/src/Data/BulkTransferSession.php',
            'packages/api/src/Internal/Bulk/TransferBulkExecutor.php',
        ], array_column($edges, 'import_file'));
    }

    public function test_boot_disables_remote_delivery_and_reports_missing_signing(): void
    {
        self::assertFalse($this->app->make(WebhookOptions::class)->deliveryEnabled);
        self::assertFalse($this->app->make(WebhookOptions::class)->workerReady);
        self::assertFalse($this->app->make(CostOptions::class)->enabled);
        self::assertFalse($this->app->bound(WebhookSigner::class));
        self::assertFalse($this->app->bound(BulkTransferFactory::class));

        $this->app['config']->set('corex-api.webhooks.delivery_enabled', true);
        $this->app['config']->set('corex-api.webhooks.worker_ready', false);

        try {
            $provider = $this->app->getProvider(ApiServiceProvider::class);
            self::assertInstanceOf(ApiServiceProvider::class, $provider);
            $provider->register();
            self::fail('Remote delivery booted without a ready worker.');
        } catch (InvalidConfiguration $exception) {
            self::assertStringContainsString('worker_ready', $exception->getMessage());
        }

        self::assertFalse($this->app->make(WebhookOptions::class)->deliveryEnabled);
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
            new TransferBulkExecutor(new BulkTransferFactoryFixture, $store, $clock, transferInstalled: false, scopes: new ArrayDataScopeCodec),
        ] as $executor) {
            try {
                $executor->submit($definition);
                self::fail('Transfer submit proceeded without the capability.');
            } catch (TransferCapabilityUnavailable $exception) {
                self::assertSame('capability_unavailable', $exception->errorDescriptor()->code);
            }
        }

        self::assertSame(0, $connection->table('sys_api_bulk_operations')->count());
        (new TransferBulkExecutor(new BulkTransferFactoryFixture, $store, $clock, transferInstalled: true, scopes: new ArrayDataScopeCodec))
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

            if (preg_match('/^use CoreX\\\\(?!Api\\\\)[^;]*\\\\Internal\\\\/m', $contents) === 1) {
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
