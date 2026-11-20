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
use CoreX\Time\Testing\FrozenClock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;
use Vaulter\Database\VaulterDatabase;
use Vaulter\Media\Contracts\Cas\BlobPins;
use Vaulter\Media\Contracts\Cas\BlobStore;

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

it('polls a committed conversion after losing dispatch and returns one Vaulter receipt for an exact replay', function (): void {
    app()->instance(MediaPolicy::class, new class implements MediaPolicy
    {
        public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
        {
            return true;
        }
    });
    app()->forgetScopedInstances();
    $context = new MediaContext(resolve(TenantContextResolver::class)->current(), 'user', 'actor');
    $image = imagecreatetruecolor(10, 5);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    $file = resolve(MediaLibrary::class)->store($context, new UploadInput(
        'conversion-dispatch-loss', UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('source.png', $bytes)), 'attachment',
    ));

    $clock = new FrozenClock(new DateTimeImmutable('2026-11-20T00:00:00Z'));
    app()->instance(ClockInterface::class, $clock);
    app()->forgetScopedInstances();

    config([
        'queue.default' => 'lost_media_dispatch',
        'queue.connections.lost_media_dispatch' => ['driver' => 'null'],
        'database.connections.media_observer' => config('database.connections.pgsql'),
    ]);
    $owner = DB::connection();
    $observer = DB::connection('media_observer');
    expect($observer->selectOne('SELECT pg_backend_pid() AS pid')->pid)->not->toBe($owner->selectOne('SELECT pg_backend_pid() AS pid')->pid);

    $owner->transaction(function () use ($context, $file, $observer): void {
        expect(resolve(MediaLibrary::class)->conversion($context, $file->id, 'thumbnail'))->toBeNull()
            ->and($observer->table('med_operations')->where('file_id', $file->id)->count())->toBe(0);
    });
    $pending = $observer->table('med_operations')->where('file_id', $file->id)->first();
    expect($pending->state)->toBe('pending')
        ->and($pending->scope_seal)->toBeString()
        ->and($pending->blob_receipt)->toBeNull();

    $operations = resolve(MediaOperationStore::class);
    $expired = $operations->claimConversion($file->id, 'thumbnail', $pending->profile_digest, $pending->operation_key);
    $clock->advance(new DateInterval('PT6M'));
    expect($operations->completeConversion($expired, 'late undo', 'text/plain', 9, 1, 1))->toBeFalse()
        ->and($observer->table('med_operations')->where('id', $pending->id)->value('blob_receipt'))->toBeNull()
        ->and(resolve(BlobPins::class)->countByOwner())->toBe(['corex.media.file' => 1]);

    config(['queue.default' => 'sync']);
    $this->artisan('corex:media:sweep', ['--limit' => 1])->assertSuccessful();
    $after = $observer->table('med_operations')->where('id', $pending->id)->first();
    $conversion = resolve(MediaLibrary::class)->conversion($context, $file->id, 'thumbnail');
    expect($after->state)->toBe('ready')
        ->and($after->operation_key)->toBe($pending->operation_key)
        ->and($conversion)->not->toBeNull()
        ->and(resolve(MediaLibrary::class)->conversion($context, $file->id, 'thumbnail')->id)->toBe($conversion->id)
        ->and($observer->table('med_conversions')->where('id', $conversion->id)->value('blob_id'))->toBe($after->blob_receipt)
        ->and($observer->table('med_operations')->where('file_id', $file->id)->count())->toBe(1)
        ->and(resolve(BlobPins::class)->countByOwner())->toBe(['corex.media.conversion' => 1, 'corex.media.file' => 1]);

    $stream = resolve(BlobStore::class)->read($after->blob_receipt);
    expect(getimagesizefromstring(stream_get_contents($stream))[0])->toBe(10);
    fclose($stream);
});
