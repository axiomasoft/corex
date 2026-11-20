<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\Data\Internal\ArrayDataScopeCodec;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\ImportExport\Contracts\ExportRunner;
use CoreX\ImportExport\Contracts\TransferRunner;
use CoreX\Operations\Bulk\Contracts\BulkTransferFactory;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Internal\SqlBulkOperationStore;
use CoreX\Operations\Bulk\Internal\TransferBulkExecutor;
use CoreX\Operations\Bulk\Internal\TransferCapabilityUnavailable;
use CoreX\Operations\Cost\Data\CostOptions;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Operations\OperationsServiceProvider;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Tests\TestCase;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use CoreX\Time\Testing\FrozenClock;
use DateTimeImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Contracts\Migration\SchemaSets;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class LoadContourBootTest extends TestCase
{
    public function test_public_surface_keeps_transfer_optional_on_laravel_13(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);
        $require = $composer['require'];

        self::assertArrayNotHasKey('public-api', $composer['extra']['corex']);
        $surface = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/contracts/php-api.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey(OperationsServiceProvider::class, $surface['symbols']);
        self::assertSame('spi', $surface['symbols'][OperationsServiceProvider::class]['layer']);
        self::assertSame('call', $surface['symbols'][OperationsServiceProvider::class]['spi_kind']);
        self::assertSame(['ext-filter', 'ext-mbstring', 'php', 'axiomasoft/corex', 'illuminate/auth', 'illuminate/console', 'illuminate/contracts', 'illuminate/database', 'illuminate/http', 'illuminate/support', 'illuminate/validation', 'psr/clock', 'symfony/http-kernel', 'axiomasoft/packman', 'axiomasoft/packman-modules', 'axiomasoft/packman-schema'], array_keys($require));
        self::assertSame('^13.0', $require['illuminate/http']);
        self::assertSame('^13.0', $require['illuminate/support']);
        self::assertSame('^13.0', $require['illuminate/validation']);
        self::assertArrayNotHasKey('axiomasoft/corex-import-export', $require);
        self::assertArrayHasKey('axiomasoft/corex-import-export', $composer['suggest']);
        self::assertMatchesRegularExpression('/\A13\./', Application::VERSION);
        self::assertSame([], $this->foreignInternalImports());

        $gate = json_decode((string) file_get_contents(dirname(__DIR__, 4).'/scripts/package-graph-gate.json'), true);
        $edges = array_values(array_filter(
            $gate['allowlist'],
            static fn (array $entry): bool => $entry['from'] === 'axiomasoft/corex-operations' && $entry['to'] === 'axiomasoft/corex-import-export',
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
        $this->app->forgetInstance(ApiSchemaConnections::class);
        $this->app->forgetInstance(SchemaSets::class);
        $this->app->forgetInstance(SchemaMigrator::class);
        $this->app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'api_tenant', 'driver' => 'sqlite', 'database' => ':memory:'],
        ]);
        $connection = DB::connection('api_tenant');

        $this->migrateSchemaSets(['axiomasoft/corex-operations:tenant'], resolve(ApiSchemaConnections::class)->tenant()->getName());
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-26T12:00:00.000000+00:00'));
        $store = new SqlBulkOperationStore(
            connection: $connection,
            scopeCodec: new ArrayDataScopeCodec,
            contexts: new CallerWorkScopeFixture($contexts),
            clock: $clock,
        );
        $scope = CallerWorkScopeFixture::scope('account-1', null, 'actor-1', hash('sha256', 'load-contour-restore'));
        $definition = new BulkOperationDefinition(
            id: 'bulk-load-contour',
            submissionId: 'submission-load-contour',
            operationId: 'notes.bulk-import',
            major: 'v1',
            contractHash: hash('sha256', 'bulk-contract'),
            encodedScope: (new ArrayDataScopeCodec)->encode($scope),
            actorId: 'actor-1',
            clientId: 'client-1',
            extensionStamp: JsonDocuments::capture([
                'point' => 'api.operation',
                'id' => 'notes.bulk-import',
                'version' => '1',
                'rights' => 'read',
                'restore_epoch' => hash('sha256', 'load-contour-restore'),
                'registryFingerprint' => hash('sha256', 'registry'),
            ]),
            inputRef: JsonDocuments::capture(['kind' => 'import', 'format' => 'ndjson', 'sha256' => hash('sha256', 'import'), 'length' => 1]),
        );

        foreach ([
            new TransferBulkExecutor(null, $store, $clock, scopes: new ArrayDataScopeCodec),
            new TransferBulkExecutor(new \CoreX\Operations\Tests\Fixtures\BulkTransferFactory, $store, $clock, transferInstalled: false, scopes: new ArrayDataScopeCodec),
        ] as $executor) {
            try {
                $executor->submit($definition);
                self::fail('Transfer submit proceeded without the capability.');
            } catch (TransferCapabilityUnavailable $exception) {
                self::assertSame('operations.capability_unavailable', $exception->errorDescriptor()->code);
            }
        }

        self::assertSame(0, $connection->table('sys_api_bulk_operations')->count());
        new TransferBulkExecutor(new \CoreX\Operations\Tests\Fixtures\BulkTransferFactory, $store, $clock, transferInstalled: true, scopes: new ArrayDataScopeCodec)
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
