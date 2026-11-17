<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Media\Contracts\ConversionDriver;
use CoreX\Media\Contracts\MediaLibrary;
use CoreX\Media\Data\ConversionInput;
use CoreX\Media\Data\ConversionOutput;
use CoreX\Media\Data\ConversionProfile;
use CoreX\Media\Exceptions\MediaOperationConflict;
use CoreX\Media\Internal\ConversionCoordinator;
use CoreX\Media\Internal\ConversionProfiles;
use CoreX\Media\Internal\MediaBlobs;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Internal\Support\MediaOptions;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;
use Vaulter\Media\Contracts\Cas\BlobPins;

function mediaRecoveryClock(): ClockInterface
{
    $clock = new class implements ClockInterface
    {
        public DateTimeImmutable $time;

        public function __construct()
        {
            $this->time = new DateTimeImmutable('2026-11-17T00:00:00+00:00');
        }

        public function now(): DateTimeImmutable
        {
            return $this->time;
        }
    };
    app()->instance(ClockInterface::class, $clock);
    app()->forgetScopedInstances();

    return $clock;
}

it('reclaims an expired purge claim and refuses its stale worker completion', function (): void {
    allowMedia();
    $clock = mediaRecoveryClock();
    $library = resolve(MediaLibrary::class);
    $file = $library->store(mediaContext(), upload('purge-crash', 'bytes'));
    $library->delete(mediaContext(), $file->id);
    $operations = resolve(MediaOperationStore::class);
    $first = $operations->claimPurge($file->id);
    expect($operations->duePurgeFileIds())->toBe([]);
    $clock->time = $clock->time->modify('+6 minutes');
    expect($operations->duePurgeFileIds())->toBe([$file->id]);
    $second = $operations->claimPurge($file->id);
    expect($second->epoch)->toBe($first->epoch + 1);
    $operations->completePurge($first);
    expect(DB::table('med_files')->where('id', $file->id)->value('state'))->toBe('deleting')
        ->and(resolve(BlobPins::class)->countByOwner())->toBe(['corex.media.file' => 1]);
    $operations->completePurge($second);
    expect(resolve(BlobPins::class)->countByOwner())->toBe([]);
});

it('reclaims conversion leases and publishes only the newest claim', function (): void {
    allowMedia();
    $clock = mediaRecoveryClock();
    $library = resolve(MediaLibrary::class);
    $file = $library->store(mediaContext(), upload('conversion-crash', 'bytes'));
    $library->conversion(mediaContext(), $file->id, 'thumbnail');
    $digest = DB::table('med_conversions')->where('file_id', $file->id)->value('profile_digest');
    $operations = resolve(MediaOperationStore::class);
    $first = $operations->claimConversion($file->id, 'thumbnail', $digest);
    expect($operations->dueConversionRequests())->toBe([]);
    $clock->time = $clock->time->modify('+6 minutes');
    expect($operations->dueConversionRequests())->toHaveCount(1);
    $second = $operations->claimConversion($file->id, 'thumbnail', $digest);
    expect($operations->completeConversion($first, 'old', 'text/plain', 3, 1, 1))->toBeFalse()
        ->and($operations->completeConversion($second, 'new', 'text/plain', 3, 1, 1))->toBeTrue()
        ->and(resolve(BlobPins::class)->countByOwner())->toBe(['corex.media.conversion' => 1, 'corex.media.file' => 1]);
});

it('rejects retained media handles after a context-only tenant switch', function (): void {
    allowMedia();
    $tenant = mediaContext()->tenant;
    $resolver = Mockery::mock(TenantContextResolver::class);
    $resolver->shouldReceive('current')->andReturnUsing(static function () use (&$tenant): TenantContext {
        return $tenant;
    });
    app()->instance(TenantContextResolver::class, $resolver);
    app()->forgetScopedInstances();
    $library = resolve(MediaLibrary::class);
    $file = $library->store(mediaContext(), upload('context-change', 'bytes'));
    $read = $library->read(mediaContext(), $file->id);
    $tenant = new TenantContext(new AccountRef('other-account', 'other', 'active', [], []));

    expect(fn () => ($read->openStream)())->toThrow(MediaOperationConflict::class)
        ->and(fn () => $library->delete(mediaContext(), $file->id))->toThrow(MediaOperationConflict::class)
        ->and(fn () => $library->store(mediaContext(), upload('stale-context', 'bytes')))->toThrow(MediaOperationConflict::class);
});

it('supersedes obsolete pending profiles instead of applying a new driver under their digest', function (): void {
    allowMedia();
    $file = resolve(MediaLibrary::class)->store(mediaContext(), upload('profile-change', 'bytes'));
    $make = static function (string $version): ConversionCoordinator {
        $driver = new readonly class($version) implements ConversionDriver
        {
            public function __construct(private string $version) {}

            public function profile(): ConversionProfile
            {
                return new ConversionProfile('custom', $this->version, 10, 10, 100, 100);
            }

            public function convert(ConversionInput $input): ConversionOutput
            {
                return new ConversionOutput($this->version, 'text/plain', 1, 1);
            }
        };

        return new ConversionCoordinator(DB::connection(), resolve(MediaBlobs::class), new ConversionProfiles([$driver]), resolve(MediaOperationStore::class), resolve(MediaOptions::class), resolve(ClockInterface::class));
    };
    $old = $make('old');
    $old->request($file->id, 'custom');
    $new = $make('new');
    $new->request($file->id, 'custom');
    $new->process($file->id, 'custom');

    expect(DB::table('med_conversions')->where('file_id', $file->id)->where('state', 'superseded')->count())->toBe(1)
        ->and(DB::table('med_conversions')->where('file_id', $file->id)->where('state', 'ready')->count())->toBe(1)
        ->and(resolve(MediaOperationStore::class)->dueConversionRequests())->toBe([]);

    // Rolling back the deployment reactivates the old digest with a fresh fenced operation.
    $old->request($file->id, 'custom');
    $old->process($file->id, 'custom');
    expect(DB::table('med_conversions')->where('file_id', $file->id)->where('state', 'ready')->count())->toBe(2)
        ->and(resolve(MediaOperationStore::class)->dueConversionRequests())->toBe([]);
});

it('does not mutate an old account retry state during failure cleanup after a tenant switch', function (): void {
    allowMedia();
    $tenant = mediaContext()->tenant;
    $resolver = Mockery::mock(TenantContextResolver::class);
    $resolver->shouldReceive('current')->andReturnUsing(static function () use (&$tenant): TenantContext {
        return $tenant;
    });
    app()->instance(TenantContextResolver::class, $resolver);
    app()->forgetScopedInstances();
    $library = resolve(MediaLibrary::class);
    $file = $library->store(mediaContext(), upload('cleanup-context', 'bytes'));
    $library->delete(mediaContext(), $file->id);
    $operations = resolve(MediaOperationStore::class);
    $claim = $operations->claimPurge($file->id);
    $tenant = new TenantContext(new AccountRef('other-account', 'other', 'active', [], []));
    $operations->markUnknown($claim);

    expect(DB::table('med_operations')->where('id', $claim->operationId)->value('state'))->toBe('claimed');
});
