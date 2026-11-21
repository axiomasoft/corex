<?php

declare(strict_types=1);

use CoreX\Media\Contracts\ConversionDriver;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Conversions\Exceptions\UnsupportedConversionProfile;
use CoreX\Media\Conversions\Laravel\ConversionCoordinator;
use CoreX\Media\Data\ConversionInput;
use CoreX\Media\Data\ConversionOutput;
use CoreX\Media\Data\ConversionProfile;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Exceptions\MediaOperationConflict;
use CoreX\Media\Files\Contracts\MediaLibrary;
use CoreX\Media\Internal\ConversionProfiles;
use CoreX\Media\Internal\Jobs\ConvertMedia;
use CoreX\Media\Internal\MediaBlobs;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Internal\Support\MediaOptions;
use CoreX\Media\Laravel\UploadedFileSource;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

function allowMediaConversions(): void
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

it('does not advertise a conversion when no matching driver is installed', function (): void {
    allowMediaConversions();
    $file = resolve(MediaLibrary::class)->store(
        conversionContext(),
        new UploadInput('unsupported-profile', UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('source.txt', 'bytes')), 'attachment'),
    );

    expect(fn () => resolve(MediaLibrary::class)->requestConversion(conversionContext(), $file->id, 'missing-profile'))->toThrow(UnsupportedConversionProfile::class)
        ->and(DB::table('med_conversions')->count())->toBe(0);
});

it('keeps malformed source conversions pending for durable retry instead of publishing output', function (): void {
    allowMediaConversions();
    $file = resolve(MediaLibrary::class)->store(
        conversionContext(),
        new UploadInput('malformed-conversion', UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('source.jpg', 'not an image')), 'attachment'),
    );

    resolve(MediaLibrary::class)->requestConversion(conversionContext(), $file->id, 'thumbnail');

    expect(fn (): mixed => app()->call([new ConvertMedia($file->id, 'thumbnail', mediaConversionKey($file->id)), 'handle']))
        ->toThrow(MediaOperationConflict::class);

    expect(DB::table('med_conversions')->where('file_id', $file->id)->value('state'))->toBe('pending')
        ->and(DB::table('med_operations')->where('file_id', $file->id)->where('kind', 'conversion')->value('state'))->toBe('unknown');
});

it('rejects output facts that exceed the immutable conversion profile', function (): void {
    allowMediaConversions();
    $profile = new ConversionProfile('bounded', 'v1', 1, 1, 1, 1);
    $driver = new readonly class($profile) implements ConversionDriver
    {
        public function __construct(private ConversionProfile $profile) {}

        public function profile(): ConversionProfile
        {
            return $this->profile;
        }

        public function convert(ConversionInput $input): ConversionOutput
        {
            return new ConversionOutput('too-large', 'application/octet-stream', 2, 2);
        }
    };

    $file = resolve(MediaLibrary::class)->store(
        conversionContext(),
        new UploadInput('bounded-conversion', UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('source.txt', 'bytes')), 'attachment'),
    );
    $profiles = new ConversionProfiles([$driver]);
    $operations = resolve(MediaOperationStore::class);
    $coordinator = new ConversionCoordinator(resolve('db')->connection(), resolve(MediaBlobs::class), $profiles, $operations, resolve(MediaOptions::class), resolve(ClockInterface::class), resolve(ExceptionHandler::class));
    $coordinator->request($file->id, 'bounded');

    expect(fn (): mixed => $coordinator->process($file->id, 'bounded', mediaConversionKey($file->id)))->toThrow(MediaOperationConflict::class);

    expect(DB::table('med_conversions')->where('file_id', $file->id)->value('state'))->toBe('pending')
        ->and(DB::table('med_operations')->where('file_id', $file->id)->where('kind', 'conversion')->value('state'))->toBe('unknown');
});
