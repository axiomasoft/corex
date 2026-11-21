<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Conversions\Contracts\ConversionOperations;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaConversionRef;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Files\Contracts\MediaLibrary;
use CoreX\Media\Internal\Jobs\ConvertMedia;
use CoreX\Media\Internal\Jobs\PurgeMedia;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Laravel\UploadedFileSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Vaulter\Media\Contracts\Cas\BlobPins;
use Vaulter\Media\Contracts\Cas\BlobStore;

function conversionContext(): MediaContext
{
    return new MediaContext(
        tenant: new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'conversion-test', 'active', [], [])),
        actorKind: 'user',
        actorId: 'actor-1',
    );
}

function thumbnailBytes(): string
{
    $image = imagecreatetruecolor(100, 50);
    imagefill($image, 0, 0, imagecolorallocate($image, 32, 64, 128));
    ob_start();
    imagejpeg($image, quality: 90);
    $bytes = ob_get_clean();

    return $bytes;
}

it('creates one bounded GD thumbnail for a file/profile/version', function (): void {
    app()->instance(MediaPolicy::class, new class implements MediaPolicy
    {
        public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
        {
            return true;
        }
    });
    app()->forgetScopedInstances();

    $bytes = thumbnailBytes();
    $file = resolve(MediaLibrary::class)->store(
        conversionContext(),
        new UploadInput('conversion-request', UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('source.jpg', $bytes)), 'attachment'),
    );

    expect(resolve(MediaLibrary::class)->requestConversion(conversionContext(), $file->id, 'thumbnail'))->toBeInstanceOf(MediaConversionRef::class);

    app()->call([new ConvertMedia($file->id, 'thumbnail', mediaConversionKey($file->id)), 'handle']);
    $conversion = resolve(MediaLibrary::class)->requestConversion(conversionContext(), $file->id, 'thumbnail');
    $stored = DB::table('med_conversions')->where('file_id', $file->id)->first();

    $read = resolve(MediaLibrary::class)->readConversion(conversionContext(), $file->id, 'thumbnail');
    $stream = ($read->openStream)();
    $converted = stream_get_contents($stream);
    fclose($stream);

    expect($conversion)->not->toBeNull()
        ->and(resolve(ConversionOperations::class)->inspect(conversionContext(), $conversion)->width)->toBe(100)
        ->and(resolve(ConversionOperations::class)->inspect(conversionContext(), $conversion)->height)->toBe(50)
        ->and($stored->state)->toBe('ready')
        ->and(getimagesizefromstring($converted)[0])->toBe(100)
        ->and($read->sha256)->toBe(hash('sha256', $converted));
});

it('purges a published conversion together with its deleting original', function (): void {
    app()->instance(MediaPolicy::class, new class implements MediaPolicy
    {
        public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
        {
            return true;
        }
    });
    app()->forgetScopedInstances();

    $file = resolve(MediaLibrary::class)->store(
        conversionContext(),
        new UploadInput('conversion-purge-request', UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('source.jpg', thumbnailBytes())), 'attachment'),
    );
    resolve(MediaLibrary::class)->requestConversion(conversionContext(), $file->id, 'thumbnail');
    app()->call([new ConvertMedia($file->id, 'thumbnail', mediaConversionKey($file->id)), 'handle']);
    $outputKey = DB::table('med_conversions')->where('file_id', $file->id)->value('blob_id');

    resolve(MediaLibrary::class)->delete(conversionContext(), $file->id);
    new PurgeMedia($file->id)->handle(resolve(MediaOperationStore::class));

    expect($outputKey)->toBeString()
        ->and(resolve(BlobPins::class)->countByOwner())->toBe([])
        ->and(mediaBlobBytes($outputKey))->not->toBeEmpty();
});

function mediaConversionKey(string $fileId): string
{
    return DB::table('med_operations')->where('file_id', $fileId)->where('kind', 'conversion')->where('state', 'pending')->value('operation_key');
}

function mediaBlobBytes(string $id): string
{
    $stream = resolve(BlobStore::class)->read($id);

    try {
        return stream_get_contents($stream);
    } finally {
        fclose($stream);
    }
}
