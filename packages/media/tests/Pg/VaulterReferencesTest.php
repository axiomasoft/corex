<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Media\Contracts\MediaLibrary;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Internal\Jobs\PurgeMedia;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Laravel\UploadedFileSource;
use CoreX\Media\Tests\PgTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Vaulter\Database\VaulterDatabase;
use Vaulter\Media\Contracts\Cas\BlobPins;

uses(PgTestCase::class)->group('pg');

it('commits and retires CoreX owner references with real Vaulter pins on PostgreSQL', function (): void {
    app()->instance(MediaPolicy::class, new class implements MediaPolicy
    {
        public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
        {
            return true;
        }
    });
    app()->forgetScopedInstances();
    $context = new MediaContext(resolve(TenantContextResolver::class)->current(), 'user', 'actor');
    $input = static fn (string $key): UploadInput => new UploadInput($key, UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('test.txt', 'shared bytes')), 'attachment');
    $library = resolve(MediaLibrary::class);
    $first = $library->store($context, $input('first'));
    $second = $library->store($context, $input('second'));
    expect(resolve(VaulterDatabase::class)->connection())->toBe(DB::connection())
        ->and(resolve(BlobPins::class)->countByOwner())->toBe(['corex.media.file' => 2]);

    expect(function () use ($library, $context, $input): void {
        resolve(VaulterDatabase::class)->transaction(function () use ($library, $context, $input): void {
            $library->store($context, $input('rollback'));

            throw new RuntimeException('rollback owner and pin');
        });
    })->toThrow(RuntimeException::class, 'rollback owner and pin');
    expect(DB::table('med_files')->where('request_key', 'rollback')->exists())->toBeFalse()
        ->and(resolve(BlobPins::class)->countByOwner())->toBe(['corex.media.file' => 2]);

    $library->delete($context, $first->id);
    new PurgeMedia($first->id)->handle(resolve(MediaOperationStore::class));
    expect(resolve(BlobPins::class)->countByOwner())->toBe(['corex.media.file' => 1]);
    $stream = ($library->read($context, $second->id)->openStream)();
    expect(stream_get_contents($stream))->toBe('shared bytes');
    fclose($stream);
});
