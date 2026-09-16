<?php

declare(strict_types=1);

use CoreX\Contracts\TenantStorage;
use CoreX\Media\Contracts\MediaLibrary;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Contracts\RelationLookup;
use CoreX\Media\Data\AttachmentInput;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Internal\DenyMediaPolicy;
use CoreX\Media\Internal\MediaAccessDenied;
use CoreX\Media\Internal\MediaFileInUse;
use CoreX\Media\Internal\MediaOperationConflict;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Jobs\PurgeMedia;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class AllowAllMediaPolicy implements MediaPolicy
{
    public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
    {
        return true;
    }
}

function mediaContext(): MediaContext
{
    return new MediaContext(
        tenant: new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'media-test', 'active', [], [])),
        actorKind: 'user',
        actorId: '11111111-1111-7111-8111-111111111112',
    );
}

function upload(string $key, string $contents): UploadInput
{
    return new UploadInput($key, UploadedFile::fake()->createWithContent('photo.txt', $contents), 'attachment');
}

function allowMedia(): void
{
    app()->instance(MediaPolicy::class, new AllowAllMediaPolicy);
    app()->forgetScopedInstances();
}

it('stores, reads, replays, attaches, and detaches private media', function (): void {
    allowMedia();
    $library = app(MediaLibrary::class);
    $relations = app(RelationLookup::class);

    $stored = $library->store(mediaContext(), upload('request-1', 'owned bytes'));
    $replayed = $library->store(mediaContext(), upload('request-1', 'owned bytes'));
    $read = $library->read(mediaContext(), $stored->id);
    $stream = ($read->openStream)();

    expect($stored->state)->toBe('ready')
        ->and($replayed->id)->toBe($stored->id)
        ->and(stream_get_contents($stream))->toBe('owned bytes');
    fclose($stream);

    $attachment = new AttachmentInput($stored->id, 'crm:contact', 'contact-1', 'avatar');
    $relations->attach(mediaContext(), $attachment);

    expect($relations->usages(mediaContext(), $stored->id))->toHaveCount(1);

    $relations->detach(mediaContext(), $attachment);

    expect($relations->usages(mediaContext(), $stored->id))->toBe([]);
});

it('denies all operations until a consumer policy explicitly allows them', function (): void {
    app()->instance(MediaPolicy::class, new DenyMediaPolicy);
    app()->forgetScopedInstances();

    expect(fn (): mixed => app(MediaLibrary::class)->store(mediaContext(), upload('request-denied', 'nope')))
        ->toThrow(MediaAccessDenied::class);
});

it('rejects a replay key with a different content digest', function (): void {
    allowMedia();
    $library = app(MediaLibrary::class);
    $library->store(mediaContext(), upload('request-conflict', 'first'));

    expect(fn (): mixed => $library->store(mediaContext(), upload('request-conflict', 'second')))
        ->toThrow(MediaOperationConflict::class);
});

it('refuses deletion while an authoritative relation exists and purges after detach', function (): void {
    allowMedia();
    $library = app(MediaLibrary::class);
    $relations = app(RelationLookup::class);
    $stored = $library->store(mediaContext(), upload('request-delete', 'erase me'));
    $attachment = new AttachmentInput($stored->id, 'crm:contact', 'contact-2', 'avatar');
    $relations->attach(mediaContext(), $attachment);

    expect(fn (): mixed => $library->delete(mediaContext(), $stored->id))->toThrow(MediaFileInUse::class);

    $relations->detach(mediaContext(), $attachment);
    $deleted = $library->delete(mediaContext(), $stored->id);

    expect($deleted->deleted)->toBeTrue()->and($deleted->purgePending)->toBeTrue();

    (new PurgeMedia($stored->id))->handle(app(MediaOperationStore::class), app(TenantStorage::class));

    expect(DB::table('med_files')->where('id', $stored->id)->value('state'))->toBe('deleted')
        ->and(app(TenantStorage::class)->disk()->exists("media/originals/{$stored->id}"))->toBeFalse();
});
