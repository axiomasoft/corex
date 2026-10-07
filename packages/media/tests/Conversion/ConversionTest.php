<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Media\Contracts\MediaLibrary;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Internal\ConversionCoordinator;
use CoreX\Media\Internal\Jobs\ConvertMedia;
use CoreX\Media\Internal\Jobs\PurgeMedia;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Laravel\UploadedFileSource;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

function conversionContext(): MediaContext
{
    return new MediaContext(
        tenant: new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'conversion-test', 'active', [], [])),
        actorKind: 'user',
        actorId: '11111111-1111-7111-8111-111111111113',
    );
}

function thumbnailBytes(): string
{
    $image = imagecreatetruecolor(100, 50);
    imagefill($image, 0, 0, imagecolorallocate($image, 32, 64, 128));
    ob_start();
    imagejpeg($image, quality: 90);
    $bytes = ob_get_clean();
    imagedestroy($image);

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

    expect(resolve(MediaLibrary::class)->conversion(conversionContext(), $file->id, 'thumbnail'))->toBeNull();

    new ConvertMedia($file->id, 'thumbnail')->handle(resolve(ConversionCoordinator::class));
    $conversion = resolve(MediaLibrary::class)->conversion(conversionContext(), $file->id, 'thumbnail');
    $stored = DB::table('med_conversions')->where('file_id', $file->id)->first();

    expect($conversion)->not->toBeNull()
        ->and($conversion->width)->toBe(100)
        ->and($conversion->height)->toBe(50)
        ->and($stored->state)->toBe('ready')
        ->and(getimagesizefromstring(resolve('filesystem')->disk('media')->get($stored->object_key))[0])->toBe(100);
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
    resolve(MediaLibrary::class)->conversion(conversionContext(), $file->id, 'thumbnail');
    new ConvertMedia($file->id, 'thumbnail')->handle(resolve(ConversionCoordinator::class));
    $outputKey = DB::table('med_conversions')->where('file_id', $file->id)->value('object_key');

    resolve(MediaLibrary::class)->delete(conversionContext(), $file->id);
    new PurgeMedia($file->id)->handle(resolve(MediaOperationStore::class), resolve(TenantStorage::class));

    expect($outputKey)->toBeString()
        ->and(resolve(TenantStorage::class)->disk()->exists($outputKey))->toBeFalse();
});
