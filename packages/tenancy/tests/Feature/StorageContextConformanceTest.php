<?php

declare(strict_types=1);

use Aws\S3\S3Client;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Storage\Exceptions\StorageCapabilityUnavailable;
use CoreX\Storage\Exceptions\StorageOperationFailed;
use CoreX\Tenancy\Runtime\Internal\Storage\ContextTenantStorage;
use CoreX\Tenancy\Runtime\Internal\Storage\ObjectTenantStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\File;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Symfony\Component\Process\Process;

if (($sdkAutoload = getenv('COREX_P3_SDK_AUTOLOAD')) !== false) {
    require_once $sdkAutoload;
}

final class ConformanceStorageContext implements TenantContextResolver
{
    public function __construct(public ?TenantContext $context) {}

    public function current(): TenantContext
    {
        return $this->context ?? throw new CentralContextException;
    }
}

function conformanceStorageContext(string $id): TenantContext
{
    return new TenantContext(account: new AccountRef(id: $id, slug: 'independent', status: 'active', features: [], limits: []));
}

beforeEach(function (): void {
    $this->storageRoot = sys_get_temp_dir().'/corex-storage-conformance-'.bin2hex(random_bytes(12));
    $this->contexts = new ConformanceStorageContext(conformanceStorageContext('00000000-0000-4000-8000-000000000101'));
    $this->storage = new ContextTenantStorage(
        contexts: $this->contexts,
        filesystems: app(FilesystemManager::class),
        durableDriver: 'local',
        durableRoot: $this->storageRoot.'/durable',
        ephemeralRoot: $this->storageRoot.'/ephemeral',
    );
});

afterEach(function (): void {
    File::deleteDirectory($this->storageRoot);
});

function assertStorageFilesystemSurface(Filesystem $disk, string $storageRoot, bool $localPaths): void
{
    $disk->makeDirectory('archive/nested');
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, 'document');
    rewind($stream);

    try {
        expect($disk->writeStream('archive/nested/document.txt', $stream))->toBeTrue();
    } finally {
        fclose($stream);
    }

    $read = $disk->readStream('archive/nested/document.txt');

    try {
        expect(stream_get_contents($read))->toBe('document');
    } finally {
        fclose($read);
    }

    $disk->prepend('archive/nested/document.txt', 'first');
    $disk->append('archive/nested/document.txt', 'last');
    expect($disk->get('archive/nested/document.txt'))->toBe("first\ndocument\nlast")
        ->and($disk->size('archive/nested/document.txt'))->toBe(19)
        ->and($disk->lastModified('archive/nested/document.txt'))->toBeInt()
        ->and($disk->setVisibility('archive/nested/document.txt', 'private'))->toBeTrue()
        ->and($disk->getVisibility('archive/nested/document.txt'))->toBe('private')
        ->and($disk->files())->toBe([])
        ->and($disk->files(null, true))->toContain('archive/nested/document.txt')
        ->and($disk->allFiles())->toContain('archive/nested/document.txt')
        ->and($disk->directories())->toContain('archive')
        ->and($disk->directories(null, true))->toContain('archive/nested')
        ->and($disk->allDirectories())->toContain('archive/nested');

    File::ensureDirectoryExists($storageRoot);
    $source = $storageRoot.'/upload.txt';
    file_put_contents($source, 'upload');
    $generated = $disk->putFile('uploads', $source);
    expect($disk->get($generated))->toBe('upload')
        ->and($disk->putFileAs('uploads', $source, 'named.txt'))->toBe('uploads/named.txt')
        ->and($disk->copy('uploads/named.txt', 'archive/copied.txt'))->toBeTrue()
        ->and($disk->move('archive/copied.txt', 'archive/moved.txt'))->toBeTrue();

    if ($localPaths) {
        expect($disk->path('archive/moved.txt'))->toBeFile();
    } else {
        expect(fn () => $disk->path('archive/moved.txt'))->toThrow(StorageOperationFailed::class);
    }
    expect($disk->delete([$generated, 'uploads/named.txt']))->toBeTrue()
        ->and($disk->deleteDirectory('archive'))->toBeTrue()
        ->and($disk->exists('archive/moved.txt'))->toBeFalse();
}

it('preserves the complete local filesystem surface with owned streams and null directories', function (): void {
    assertStorageFilesystemSurface(disk: $this->storage->disk(), storageRoot: $this->storageRoot, localPaths: true);
});

it('refuses local usage through a replaced account root', function (): void {
    $disk = $this->storage->disk();
    $disk->put('document.txt', 'archive');
    $root = dirname($disk->path('document.txt'));
    rename($root, $root.'-original');
    symlink($root.'-original', $root);

    try {
        expect(fn () => $this->storage->usageBytes())->toThrow(StorageOperationFailed::class);
    } finally {
        unlink($root);
    }
});

describe('native object integration', function (): void {
    beforeEach(function (): void {
        $configPath = getenv('COREX_P3_STORAGE_CONFIG');

        if ($configPath === false) {
            $this->markTestSkipped('Explicit isolated native SDK/service configuration is required.');
        }
        $this->objectConfig = json_decode(file_get_contents($configPath), true, flags: JSON_THROW_ON_ERROR);
        $this->objectStorage = new ObjectTenantStorage(
            contexts: $this->contexts,
            filesystems: app(FilesystemManager::class),
            durableConfig: $this->objectConfig,
            ephemeralRoot: $this->storageRoot.'/ephemeral',
        );
        foreach (['00000000-0000-4000-8000-000000000101', '00000000-0000-4000-8000-000000000202'] as $accountId) {
            $this->contexts->context = conformanceStorageContext($accountId);
            $this->objectStorage->disk()->deleteDirectory('');
        }
        $this->contexts->context = conformanceStorageContext('00000000-0000-4000-8000-000000000101');
    });

    it('executes the same complete filesystem surface through the real SDK', function (): void {
        assertStorageFilesystemSurface(disk: $this->objectStorage->disk(), storageRoot: $this->storageRoot, localPaths: false);
    });

    it('isolates independent archive and laboratory object prefixes and ephemeral local storage', function (): void {
        $archive = $this->objectStorage->disk();
        $archive->put('document.txt', 'archive');
        $this->objectStorage->ephemeralDisk()->put('document.txt', 'temporary');
        expect($archive->getVisibility('document.txt'))->toBe('private')
            ->and($this->objectStorage->ephemeralDisk()->get('document.txt'))->toBe('temporary')
            ->and($this->objectStorage->capabilities()->usage)->toBeFalse()
            ->and($this->objectStorage->capabilities()->export)->toBeFalse()
            ->and(fn () => $this->objectStorage->usageBytes())->toThrow(StorageCapabilityUnavailable::class)
            ->and(fn () => $this->objectStorage->exportArchive())->toThrow(StorageCapabilityUnavailable::class)
            ->and(fn () => $archive->url('document.txt'))->toThrow(StorageOperationFailed::class)
            ->and(fn () => $archive->setVisibility('document.txt', 'public'))->toThrow(StorageOperationFailed::class)
            ->and(fn () => $archive->put('public.txt', 'unsafe', ['visibility' => 'public']))->toThrow(StorageOperationFailed::class)
            ->and(fn () => $archive->put('%255cescape', 'unsafe'))->toThrow(StorageOperationFailed::class)
            ->and(fn () => $archive->delete(['document.txt', '%252e%252e/escape']))->toThrow(StorageOperationFailed::class);

        $this->contexts->context = conformanceStorageContext('00000000-0000-4000-8000-000000000202');
        $lab = $this->objectStorage->disk();
        expect($lab->exists('document.txt'))->toBeFalse()
            ->and($this->objectStorage->ephemeralDisk()->exists('document.txt'))->toBeFalse()
            ->and(fn () => $archive->readStream('document.txt'))->toThrow(StorageOperationFailed::class);
        $lab->put('document.txt', 'lab');
        $client = new S3Client([
            'version' => 'latest', 'region' => $this->objectConfig['region'], 'endpoint' => $this->objectConfig['endpoint'],
            'use_path_style_endpoint' => true, 'credentials' => ['key' => $this->objectConfig['key'], 'secret' => $this->objectConfig['secret']],
        ]);
        foreach (['00000000-0000-4000-8000-000000000101' => 'archive', '00000000-0000-4000-8000-000000000202' => 'lab'] as $id => $body) {
            expect((string) $client->getObject(['Bucket' => $this->objectConfig['bucket'], 'Key' => 'tenants/'.$id.'/document.txt'])['Body'])->toBe($body);
        }
        $this->contexts->context = null;
        expect(fn () => $lab->files())->toThrow(CentralContextException::class);
    });

    it('refuses configured provider connection failure without a local or other account fallback', function (): void {
        $config = array_merge($this->objectConfig, ['endpoint' => 'http://127.0.0.1:1']);
        $storage = new ObjectTenantStorage(contexts: $this->contexts, filesystems: app(FilesystemManager::class), durableConfig: $config, ephemeralRoot: $this->storageRoot.'/ephemeral');
        expect(fn () => $storage->disk()->get('document.txt'))->toThrow(StorageOperationFailed::class)
            ->and(fn () => $storage->disk()->put('document.txt', 'wrong-fallback'))->toThrow(StorageOperationFailed::class);
    });
})->group('p3-native');

it('denies every retained handle entry point after switching account or ending context', function (): void {
    $disk = $this->storage->disk();
    $disk->put('safe.txt', 'archive');
    $operations = [
        fn () => $disk->path('safe.txt'), fn () => $disk->url('safe.txt'),
        fn () => $disk->exists('safe.txt'), fn () => $disk->get('safe.txt'), fn () => $disk->readStream('safe.txt'),
        fn () => $disk->put('safe.txt', 'lab'), fn () => $disk->putFile('uploads', __FILE__),
        fn () => $disk->putFileAs('uploads', __FILE__, 'source.php'),
        fn () => $disk->writeStream('safe.txt', null), fn () => $disk->getVisibility('safe.txt'),
        fn () => $disk->setVisibility('safe.txt', 'private'), fn () => $disk->prepend('safe.txt', 'lab'),
        fn () => $disk->append('safe.txt', 'lab'), fn () => $disk->delete(['safe.txt']),
        fn () => $disk->copy('safe.txt', 'copy.txt'), fn () => $disk->move('safe.txt', 'move.txt'),
        fn () => $disk->size('safe.txt'), fn () => $disk->lastModified('safe.txt'),
        fn () => $disk->files(), fn () => $disk->allFiles(), fn () => $disk->directories(),
        fn () => $disk->allDirectories(), fn () => $disk->makeDirectory('lab'), fn () => $disk->deleteDirectory('archive'),
    ];
    $this->contexts->context = conformanceStorageContext('00000000-0000-4000-8000-000000000202');
    foreach ($operations as $operation) {
        expect($operation)->toThrow(StorageOperationFailed::class);
    }
    expect($this->storage->disk()->exists('safe.txt'))->toBeFalse();
    $this->contexts->context = null;
    foreach ($operations as $operation) {
        expect($operation)->toThrow(CentralContextException::class);
    }
});

it('validates batch and destination paths before any part of the operation mutates storage', function (string $path): void {
    $disk = $this->storage->disk();
    $disk->put('safe.txt', 'archive');
    foreach ([fn () => $disk->delete(['safe.txt', $path]), fn () => $disk->copy('safe.txt', $path),
        fn () => $disk->move($path, 'safe.txt'), fn () => $disk->putFileAs('uploads', __FILE__, $path)] as $operation) {
        expect($operation)->toThrow(StorageOperationFailed::class);
    }
    expect($disk->get('safe.txt'))->toBe('archive');
})->with(['../escape', '%252e%252e/escape', '/absolute', '%2fabsolute', 'C:escape', '%43%3aescape', '\\escape', '%255cescape', "nul\0", 'nul%2500']);

it('refuses the optional object branch explicitly when its native SDK is absent', function (): void {
    if (class_exists(AwsS3V3Adapter::class)) {
        $process = new Process([PHP_BINARY, __DIR__.'/../Support/StorageSdkAbsenceProbe.php']);
        $process->mustRun();
        expect(trim($process->getOutput()))->toBe('sdk-absent: denied before context or storage access');

        return;
    }
    expect(fn () => new ObjectTenantStorage(
        contexts: $this->contexts,
        filesystems: app(FilesystemManager::class),
        durableConfig: ['driver' => 's3', 'bucket' => 'unconfigured'],
        ephemeralRoot: $this->storageRoot.'/ephemeral',
    ))->toThrow(StorageCapabilityUnavailable::class);
});
