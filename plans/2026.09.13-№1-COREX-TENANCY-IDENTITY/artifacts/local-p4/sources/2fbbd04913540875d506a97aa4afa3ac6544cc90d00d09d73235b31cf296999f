<?php

declare(strict_types=1);

use CoreX\Contracts\TenantStorage;
use CoreX\Exceptions\CentralContextException;
use CoreX\Storage\StorageOperationFailed;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\TenantContext;
use Illuminate\Support\Facades\File;

final class TenantStorageContextResolverForP22 implements TenantContextResolver
{
    public function __construct(public ?TenantContext $context) {}

    public function current(): TenantContext
    {
        return $this->context ?? throw new CentralContextException;
    }
}

function tenantStorageContextForP22(string $id): TenantContext
{
    return new TenantContext(
        account: new AccountRef(id: $id, slug: 'storage-test', status: 'active', features: [], limits: []),
    );
}

beforeEach(function (): void {
    $this->storageRoot = sys_get_temp_dir().'/corex-tenancy-storage-'.bin2hex(random_bytes(8));
    $this->storageContext = new TenantStorageContextResolverForP22(
        context: tenantStorageContextForP22('11111111-1111-7111-8111-111111111111'),
    );

    app()->instance(TenantContextResolver::class, $this->storageContext);
    app()->forgetScopedInstances();
});

afterEach(function (): void {
    File::deleteDirectory($this->storageRoot);
});

it('keeps local and fake-cloud durable storage semantically identical', function (string $driver): void {
    config([
        'tenancy.storage.durable_driver' => $driver,
        'tenancy.storage.durable_root' => $this->storageRoot.'/durable',
        'tenancy.storage.ephemeral_root' => $this->storageRoot.'/ephemeral',
    ]);
    app()->forgetScopedInstances();

    $storage = app(TenantStorage::class);
    $handleA = $storage->disk();

    expect($storage->usageBytes())->toBe(0);

    $handleA->put('reports/summary.txt', 'account-a');
    $storage->ephemeralDisk()->put('reports/summary.txt', 'temporary');

    $accountAPath = $this->storageRoot.'/durable/tenants/11111111-1111-7111-8111-111111111111/reports/summary.txt';

    expect($handleA->get('reports/summary.txt'))->toBe('account-a')
        ->and($storage->ephemeralDisk()->get('reports/summary.txt'))->toBe('temporary')
        ->and($storage->usageBytes())->toBe(9)
        ->and($accountAPath)->toBeFile()
        ->and($this->storageRoot.'/durable/tenants/tenants')->not->toBeDirectory();

    $this->storageContext->context = tenantStorageContextForP22('22222222-2222-7222-8222-222222222222');

    expect(fn () => $handleA->get('reports/summary.txt'))->toThrow(StorageOperationFailed::class)
        ->and($storage->disk()->exists('reports/summary.txt'))->toBeFalse();
})->with(['local', 'fake-s3']);

it('rejects traversal and a retained worker handle after tenancy clears', function (): void {
    config([
        'tenancy.storage.durable_driver' => 'fake-s3',
        'tenancy.storage.durable_root' => $this->storageRoot.'/durable',
        'tenancy.storage.ephemeral_root' => $this->storageRoot.'/ephemeral',
    ]);
    app()->forgetScopedInstances();

    $storage = app(TenantStorage::class);
    $handle = $storage->disk();
    $handle->put('safe.txt', 'safe');

    expect(fn () => $handle->put('%252e%252e/escape.txt', 'no'))->toThrow(StorageOperationFailed::class)
        ->and(fn () => $handle->copy('safe.txt', '../escape.txt'))->toThrow(StorageOperationFailed::class);

    $this->storageContext->context = null;

    expect(fn () => $handle->get('safe.txt'))->toThrow(CentralContextException::class)
        ->and(fn () => $storage->disk())->toThrow(CentralContextException::class);
});
