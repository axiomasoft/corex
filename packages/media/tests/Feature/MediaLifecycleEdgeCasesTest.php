<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Media\Contracts\MediaLibrary;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Contracts\RelationLookup;
use CoreX\Media\Data\AttachmentInput;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Internal\ConversionCoordinator;
use CoreX\Media\Internal\Jobs\PurgeMedia;
use CoreX\Media\Internal\MediaAccessDenied;
use CoreX\Media\Internal\MediaOperationConflict;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Internal\SqlMediaLibrary;
use CoreX\Media\Internal\Support\MediaOptions;
use CoreX\Media\Laravel\UploadedFileSource;
use CoreX\Media\MediaModule;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

function mediaEdgeContext(): MediaContext
{
    return new MediaContext(
        tenant: new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'media-edge-test', 'active', [], [])),
        actorKind: 'user',
        actorId: '11111111-1111-7111-8111-111111111115',
    );
}

function allowMediaEdges(): void
{
    app()->instance(MediaPolicy::class, new class implements MediaPolicy
    {
        public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
        {
            return true;
        }
    });
    app()->forgetScopedInstances();
}

function edgeUpload(string $requestKey, string $contents): UploadInput
{
    return new UploadInput($requestKey, UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('safe.txt', $contents)), 'attachment');
}

it('keeps staged uploads private and schedules their purge when storage write fails', function (): void {
    allowMediaEdges();
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('writeStream')->once()->andReturnFalse();
    $storage = Mockery::mock(TenantStorage::class);
    $storage->shouldReceive('disk')->andReturn($disk);
    app()->instance(TenantStorage::class, $storage);
    app()->forgetScopedInstances();

    expect(fn (): mixed => resolve(MediaLibrary::class)->store(mediaEdgeContext(), edgeUpload('failed-write', 'partial bytes')))
        ->toThrow(MediaOperationConflict::class);

    expect(DB::table('med_files')->where('request_key', 'failed-write')->value('state'))->toBe('deleting')
        ->and(DB::table('med_operations')->where('kind', 'purge')->count())->toBe(1);
});

it('keeps attachment operations idempotent and rejects unavailable files', function (): void {
    allowMediaEdges();
    $library = resolve(MediaLibrary::class);
    $relations = resolve(RelationLookup::class);
    $file = $library->store(mediaEdgeContext(), edgeUpload('relation-idempotent', 'bytes'));
    $attachment = new AttachmentInput($file->id, 'crm:contact', 'contact-edge', 'avatar');

    $relations->attach(mediaEdgeContext(), $attachment);
    $relations->attach(mediaEdgeContext(), $attachment);
    $relations->detach(mediaEdgeContext(), $attachment);
    $relations->detach(mediaEdgeContext(), $attachment);

    expect($relations->usages(mediaEdgeContext(), $file->id))->toBe([]);
    expect(fn (): mixed => $relations->attach(mediaEdgeContext(), new AttachmentInput('missing-file', 'crm:contact', 'contact-edge', 'avatar')))
        ->toThrow(MediaOperationConflict::class);
});

it('marks a failed purge unknown instead of publishing a false delete', function (): void {
    allowMediaEdges();
    $library = resolve(MediaLibrary::class);
    $file = $library->store(mediaEdgeContext(), edgeUpload('purge-unknown', 'bytes'));
    $library->delete(mediaEdgeContext(), $file->id);

    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('delete')->once()->andReturnFalse();
    $storage = Mockery::mock(TenantStorage::class);
    $storage->shouldReceive('disk')->andReturn($disk);

    expect(fn (): mixed => new PurgeMedia($file->id)->handle(resolve(MediaOperationStore::class), $storage))
        ->toThrow(MediaOperationConflict::class);

    expect(DB::table('med_files')->where('id', $file->id)->value('state'))->toBe('deleting')
        ->and(DB::table('med_operations')->where('file_id', $file->id)->where('kind', 'purge')->value('state'))->toBe('unknown');
});

it('rejects malformed trusted media inputs before creating storage keys', function (): void {
    expect(fn (): AttachmentInput => new AttachmentInput('', 'crm:contact', 'contact-edge', 'avatar'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn (): UploadInput => new UploadInput('', UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('safe.txt', 'bytes')), 'attachment'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn (): UploadInput => new UploadInput("request\0key", UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('safe.txt', 'bytes')), 'attachment'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn (): MediaContext => new MediaContext(mediaEdgeContext()->tenant, 'user', null))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an unregistered purpose and a server-detected executable MIME type', function (): void {
    allowMediaEdges();
    $library = resolve(MediaLibrary::class);

    expect(fn (): mixed => $library->store(
        mediaEdgeContext(),
        new UploadInput('unknown-purpose', UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('safe.txt', 'bytes')), 'unknown'),
    ))->toThrow(MediaOperationConflict::class);
    expect(fn (): mixed => $library->store(
        mediaEdgeContext(),
        new UploadInput('executable-mime', UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('unsafe.php', '<?php echo 1;')), 'attachment'),
    ))->toThrow(MediaOperationConflict::class);

    expect(DB::table('med_files')->whereIn('request_key', ['unknown-purpose', 'executable-mime'])->count())->toBe(0);
});

it('rechecks policy before publishing uploaded bytes and when an authorized handle is consumed', function (): void {
    $policy = new class implements MediaPolicy
    {
        public int $storeChecks = 0;

        public bool $allowsRead = true;

        public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
        {
            return match ($action) {
                MediaAction::Store => ++$this->storeChecks < 4,
                MediaAction::Read => $this->allowsRead,
                default => true,
            };
        }
    };
    $library = new SqlMediaLibrary(
        resolve('db')->connection(),
        resolve(TenantStorage::class),
        $policy,
        resolve(MediaOperationStore::class),
        resolve(ConversionCoordinator::class),
        resolve(MediaOptions::class),
        resolve(ClockInterface::class),
    );

    $file = $library->store(mediaEdgeContext(), edgeUpload('read-recheck', 'bytes'));
    $read = $library->read(mediaEdgeContext(), $file->id);
    $policy->allowsRead = false;

    expect(fn (): mixed => ($read->openStream)())->toThrow(MediaAccessDenied::class);

    expect(fn (): mixed => $library->store(mediaEdgeContext(), edgeUpload('publish-recheck', 'bytes')))
        ->toThrow(MediaAccessDenied::class);
    expect(DB::table('med_files')->where('request_key', 'publish-recheck')->value('state'))->toBe('deleting')
        ->and(DB::table('med_operations')->where('file_id', DB::table('med_files')->where('request_key', 'publish-recheck')->value('id'))->where('kind', 'purge')->count())->toBe(1);
});

it('publishes media as a standalone CoreX module', function (): void {
    $manifest = (new MediaModule)->manifest();

    expect($manifest->composerName)->toBe('axiomasoft/corex-media')
        ->and($manifest->tablePrefix)->toBe('med');
});

it('reports an unavailable storage stream instead of returning a non-resource from a ready media reference', function (): void {
    allowMediaEdges();
    $library = resolve(MediaLibrary::class);
    $file = $library->store(mediaEdgeContext(), edgeUpload('unavailable-stream', 'ready bytes'));
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('readStream')->once()->andReturnNull();
    $storage = Mockery::mock(TenantStorage::class);
    $storage->shouldReceive('disk')->andReturn($disk);
    app()->instance(TenantStorage::class, $storage);
    app()->forgetScopedInstances();
    $read = resolve(MediaLibrary::class)->read(mediaEdgeContext(), $file->id);

    expect(fn (): mixed => ($read->openStream)())->toThrow(MediaOperationConflict::class, 'Media stream is unavailable.');
});
