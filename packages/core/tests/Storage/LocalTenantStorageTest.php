<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Storage\Exceptions\StorageCapabilityUnavailable;
use CoreX\Storage\Exceptions\StorageOperationFailed;
use Illuminate\Support\Facades\File;

final class MutableStorageContextResolver implements TenantContextResolver
{
    public function __construct(public TenantContext $context) {}

    public function current(): TenantContext
    {
        return $this->context;
    }
}

function storageContext(string $id): TenantContext
{
    return new TenantContext(account: new AccountRef(id: $id, slug: 'test', status: 'active', features: [], limits: []));
}

beforeEach(function (): void {
    $this->storageRoot = sys_get_temp_dir().'/corex-storage-'.bin2hex(random_bytes(8));
    config([
        'corex.storage.durable_root' => $this->storageRoot.'/durable',
        'corex.storage.ephemeral_root' => $this->storageRoot.'/ephemeral',
    ]);
    app()->forgetInstance(CoreOptions::class);
    $this->resolver = new MutableStorageContextResolver(storageContext('11111111-1111-7111-8111-111111111111'));
    app()->instance(TenantContextResolver::class, $this->resolver);
    app()->forgetInstance(TenantStorage::class);
});

afterEach(function (): void {
    File::deleteDirectory($this->storageRoot);
});

it('isolates durable and ephemeral storage by the current account and invalidates retained handles', function (): void {
    $storage = resolve(TenantStorage::class);
    $accountA = $storage->disk();

    expect($storage->usageBytes())->toBe(0);

    $accountA->put('document.txt', 'A');
    $storage->ephemeralDisk()->put('document.txt', 'temporary');

    expect($accountA->get('document.txt'))->toBe('A')
        ->and($storage->ephemeralDisk()->get('document.txt'))->toBe('temporary')
        ->and($storage->usageBytes())->toBe(1)
        ->and($storage->capabilities()->usage)->toBeTrue()
        ->and($storage->capabilities()->export)->toBeFalse();

    expect(fn () => $accountA->url('document.txt'))->toThrow(StorageOperationFailed::class);

    $this->resolver->context = storageContext('22222222-2222-7222-8222-222222222222');

    expect(fn () => $accountA->get('document.txt'))->toThrow(StorageOperationFailed::class)
        ->and($storage->disk()->exists('document.txt'))->toBeFalse();
});

it('rejects path escape and reports unsupported export explicitly', function (): void {
    $storage = resolve(TenantStorage::class);

    expect(fn () => $storage->disk()->put('../escape.txt', 'no'))->toThrow(StorageOperationFailed::class)
        ->and(fn () => $storage->disk()->put('%252e%252e/escape.txt', 'no'))->toThrow(StorageOperationFailed::class)
        ->and(fn () => $storage->disk()->put('/escape.txt', 'no'))->toThrow(StorageOperationFailed::class)
        ->and(fn () => $storage->exportArchive())->toThrow(StorageCapabilityUnavailable::class);
});

it('keeps all relative filesystem operations inside the account root', function (): void {
    $storage = resolve(TenantStorage::class);
    $disk = $storage->disk();
    $source = $this->storageRoot.'/source.txt';
    file_put_contents($source, 'uploaded');

    expect($disk->putFileAs('uploads', $source, 'source.txt'))->toBe('uploads/source.txt')
        ->and($disk->copy('uploads/source.txt', 'copies/source.txt'))->toBeTrue()
        ->and($disk->move('copies/source.txt', 'moved/source.txt'))->toBeTrue()
        ->and($disk->files('moved'))->toContain('moved/source.txt')
        ->and(fn () => $disk->copy('uploads/source.txt', '../escape.txt'))->toThrow(StorageOperationFailed::class)
        ->and($disk->delete('moved/source.txt'))->toBeTrue()
        ->and($disk->exists('moved/source.txt'))->toBeFalse();
});

it('rejects symbolic links within an account root', function (): void {
    $storage = resolve(TenantStorage::class);
    $disk = $storage->disk();
    $disk->put('source.txt', 'secret');
    $root = $this->storageRoot.'/durable/tenants/11111111-1111-7111-8111-111111111111';

    symlink($root.'/source.txt', $root.'/linked.txt');

    expect(fn () => $disk->get('linked.txt'))->toThrow(StorageOperationFailed::class);
});

it('rejects encoded backslashes and NUL bytes before the driver sees them', function (string $path): void {
    expect(fn () => resolve(TenantStorage::class)->disk()->put($path, 'unsafe'))
        ->toThrow(StorageOperationFailed::class);
})->with(['%5cescape.txt', '%255cescape.txt', 'document%00.txt', 'document%2500.txt']);

it('rejects a replaced account root even when listing without a directory', function (): void {
    $disk = resolve(TenantStorage::class)->disk();
    $disk->put('document.txt', 'owned');
    $root = dirname($disk->path('document.txt'));
    rename($root, $root.'-original');
    symlink($root.'-original', $root);

    expect(fn () => $disk->allFiles())->toThrow(StorageOperationFailed::class);
    expect(fn () => resolve(TenantStorage::class)->usageBytes())->toThrow(StorageOperationFailed::class);
    unlink($root);
});
