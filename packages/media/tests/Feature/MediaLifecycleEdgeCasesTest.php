<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Media\Attachments\Contracts\MediaAttachments;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Conversions\Laravel\ConversionCoordinator;
use CoreX\Media\Data\AttachmentInput;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Data\UploadFile;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Exceptions\MediaAccessDenied;
use CoreX\Media\Exceptions\MediaOperationConflict;
use CoreX\Media\Files\Contracts\MediaLibrary;
use CoreX\Media\Files\Laravel\SqlMediaLibrary;
use CoreX\Media\Internal\Jobs\PurgeMedia;
use CoreX\Media\Internal\MediaBlobs;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Internal\Support\MediaOptions;
use CoreX\Media\Laravel\UploadedFileSource;
use CoreX\Media\MediaModule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;
use Vaulter\Media\Contracts\Cas\BlobPins;
use Vaulter\Media\Contracts\Cas\BlobStore;

function mediaEdgeContext(): MediaContext
{
    return new MediaContext(
        tenant: new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'media-edge-test', 'active', [], [])),
        actorKind: 'user',
        actorId: 'actor-1',
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

it('rolls back the owner reference when Vaulter commit fails', function (): void {
    allowMediaEdges();
    $store = Mockery::mock(BlobStore::class);
    $store->shouldReceive('commit')->once()->andThrow(new RuntimeException('storage unavailable'));
    app()->instance(BlobStore::class, $store);
    app()->forgetScopedInstances();

    expect(fn (): mixed => resolve(MediaLibrary::class)->store(mediaEdgeContext(), edgeUpload('failed-write', 'partial bytes')))
        ->toThrow(RuntimeException::class, 'storage unavailable');
    expect(DB::table('med_files')->where('request_key', 'failed-write')->exists())->toBeFalse()
        ->and(resolve(BlobPins::class)->countByOwner())->toBe([]);
});

it('keeps attachment operations idempotent and rejects unavailable files', function (): void {
    allowMediaEdges();
    $library = resolve(MediaLibrary::class);
    $relations = resolve(MediaAttachments::class);
    $file = $library->store(mediaEdgeContext(), edgeUpload('relation-idempotent', 'bytes'));
    $attachment = new AttachmentInput($file->id, 'crm.contact', 'contact-edge', 'avatar');

    $relations->attach(mediaEdgeContext(), $attachment);
    $relations->attach(mediaEdgeContext(), $attachment);
    $relations->detach(mediaEdgeContext(), $attachment);
    $relations->detach(mediaEdgeContext(), $attachment);

    expect($relations->usages(mediaEdgeContext(), $file->id))->toBe([]);
    expect(fn (): mixed => $relations->attach(mediaEdgeContext(), new AttachmentInput('missing-file', 'crm.contact', 'contact-edge', 'avatar')))
        ->toThrow(MediaOperationConflict::class);
});

it('marks a failed purge unknown instead of publishing a false delete', function (): void {
    allowMediaEdges();
    $library = resolve(MediaLibrary::class);
    $file = $library->store(mediaEdgeContext(), edgeUpload('purge-unknown', 'bytes'));
    $library->delete(mediaEdgeContext(), $file->id);

    $pins = Mockery::mock(BlobPins::class);
    $pins->shouldReceive('unpin')->once()->andThrow(new RuntimeException('pin unavailable'));
    app()->instance(BlobPins::class, $pins);
    app()->forgetScopedInstances();

    expect(fn (): mixed => new PurgeMedia($file->id)->handle(resolve(MediaOperationStore::class)))
        ->toThrow(RuntimeException::class, 'pin unavailable');
    expect(DB::table('med_files')->where('id', $file->id)->value('state'))->toBe('deleting')
        ->and(DB::table('med_files')->where('id', $file->id)->value('blob_id'))->not->toBeNull()
        ->and(DB::table('med_operations')->where('file_id', $file->id)->where('kind', 'purge')->value('state'))->toBe('unknown');
});

it('rejects malformed trusted media inputs before creating storage keys', function (): void {
    expect(fn (): AttachmentInput => new AttachmentInput('', 'crm.contact', 'contact-edge', 'avatar'))
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

function pngHeader(int $width, int $height): string
{
    $ihdr = 'IHDR'.pack('NN', $width, $height)."\x08\x02\x00\x00\x00";

    return "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr));
}

it('admits a directly constructed upload only when it is well formed', function (UploadFile $file): void {
    allowMediaEdges();
    $library = resolve(MediaLibrary::class);

    expect($library->store(mediaEdgeContext(), new UploadInput('direct-ok', new UploadFile('bytes', 'safe.txt', true, 5, 'text/plain'), 'attachment'))->id)->not->toBe('')
        ->and(fn (): mixed => $library->store(mediaEdgeContext(), new UploadInput('direct-bad', $file, 'attachment')))->toThrow(MediaOperationConflict::class)
        ->and(DB::table('med_files')->where('request_key', 'direct-bad')->count())->toBe(0);
})->with([
    'flagged invalid' => fn (): UploadFile => new UploadFile('bytes', 'safe.txt', false, 5, 'text/plain'),
    'no bytes' => fn (): UploadFile => new UploadFile(null, 'safe.txt', true, 5, 'text/plain'),
    'empty name' => fn (): UploadFile => new UploadFile('bytes', '', true, 5, 'text/plain'),
    'slash in name' => fn (): UploadFile => new UploadFile('bytes', '../safe.txt', true, 5, 'text/plain'),
    'backslash in name' => fn (): UploadFile => new UploadFile('bytes', 'dir\\safe.txt', true, 5, 'text/plain'),
    'NUL in name' => fn (): UploadFile => new UploadFile('bytes', "safe\0.txt", true, 5, 'text/plain'),
    'empty file' => fn (): UploadFile => new UploadFile('', 'safe.txt', true, 0, 'text/plain'),
    'size mismatch' => fn (): UploadFile => new UploadFile('bytes', 'safe.txt', true, 4, 'text/plain'),
    'over max_upload_bytes' => fn (): UploadFile => new UploadFile(str_repeat('a', 10 * 1024 * 1024 + 1), 'big.txt', true, 10 * 1024 * 1024 + 1, 'text/plain'),
    'image over max_image_pixels' => fn (): UploadFile => new UploadFile(pngHeader(100_000, 100_000), 'bomb.png', true, strlen(pngHeader(100_000, 100_000)), 'image/png'),
]);

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
        resolve(MediaBlobs::class),
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
    expect(DB::table('med_files')->where('request_key', 'publish-recheck')->exists())->toBeFalse()
        ->and(resolve(BlobPins::class)->countByOwner())->toBe(['corex.media.file' => 1]);
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
    $store = Mockery::mock(BlobStore::class);
    $store->shouldReceive('read')->once()->andReturnNull();
    app()->instance(BlobStore::class, $store);
    app()->forgetScopedInstances();
    $read = resolve(MediaLibrary::class)->read(mediaEdgeContext(), $file->id);

    expect(fn (): mixed => ($read->openStream)())->toThrow(MediaOperationConflict::class, 'Media stream is unavailable.');
});
