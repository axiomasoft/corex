<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Media\Attachments\Contracts\MediaAttachments;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Data\AttachmentInput;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Exceptions\MediaAccessDenied;
use CoreX\Media\Exceptions\MediaFileInUse;
use CoreX\Media\Exceptions\MediaOperationConflict;
use CoreX\Media\Files\Contracts\MediaLibrary;
use CoreX\Media\Internal\DenyMediaPolicy;
use CoreX\Media\Internal\Jobs\PurgeMedia;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Laravel\UploadedFileSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Vaulter\Media\Contracts\Cas\BlobPins;

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
        actorId: 'actor-1',
    );
}

function upload(string $key, string $contents): UploadInput
{
    return new UploadInput($key, UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('photo.txt', $contents)), 'attachment');
}

function allowMedia(): void
{
    app()->instance(MediaPolicy::class, new AllowAllMediaPolicy);
    app()->forgetScopedInstances();
}

it('stores, reads, replays, attaches, and detaches private media', function (): void {
    allowMedia();
    $library = resolve(MediaLibrary::class);
    $relations = resolve(MediaAttachments::class);

    $stored = $library->store(mediaContext(), upload('request-1', 'owned bytes'));
    $replayed = $library->store(mediaContext(), upload('request-1', 'owned bytes'));
    $read = $library->read(mediaContext(), $stored->id);
    $stream = ($read->openStream)();

    expect($stored->state)->toBe('ready')
        ->and($replayed->id)->toBe($stored->id)
        ->and(stream_get_contents($stream))->toBe('owned bytes');
    fclose($stream);

    $attachment = new AttachmentInput($stored->id, 'crm.contact', 'contact-1', 'avatar');
    $relations->attach(mediaContext(), $attachment);

    expect($relations->usages(mediaContext(), $stored->id))->toHaveCount(1);

    $relations->detach(mediaContext(), $attachment);

    expect($relations->usages(mediaContext(), $stored->id))->toBe([]);
});

it('denies all operations until a consumer policy explicitly allows them', function (): void {
    app()->instance(MediaPolicy::class, new DenyMediaPolicy);
    app()->forgetScopedInstances();

    expect(fn (): mixed => resolve(MediaLibrary::class)->store(mediaContext(), upload('request-denied', 'nope')))
        ->toThrow(MediaAccessDenied::class);
});

it('rejects a replay key with a different content digest', function (): void {
    allowMedia();
    $library = resolve(MediaLibrary::class);
    $library->store(mediaContext(), upload('request-conflict', 'first'));

    expect(fn (): mixed => $library->store(mediaContext(), upload('request-conflict', 'second')))
        ->toThrow(MediaOperationConflict::class);
});

it('refuses deletion while an authoritative relation exists and purges after detach', function (): void {
    allowMedia();
    $library = resolve(MediaLibrary::class);
    $relations = resolve(MediaAttachments::class);
    $stored = $library->store(mediaContext(), upload('request-delete', 'erase me'));
    $attachment = new AttachmentInput($stored->id, 'crm.contact', 'contact-2', 'avatar');
    $relations->attach(mediaContext(), $attachment);

    expect(fn (): mixed => $library->delete(mediaContext(), $stored->id))->toThrow(MediaFileInUse::class);

    $relations->detach(mediaContext(), $attachment);
    $deleted = $library->delete(mediaContext(), $stored->id);

    expect($deleted->deleted)->toBeTrue()->and($deleted->purgePending)->toBeTrue();

    new PurgeMedia($stored->id)->handle(resolve(MediaOperationStore::class));

    expect(DB::table('med_files')->where('id', $stored->id)->value('state'))->toBe('deleted')
        ->and(resolve(BlobPins::class)->countByOwner())->toBe([]);
});

function denyMediaAction(MediaAction $denied): void
{
    app()->instance(MediaPolicy::class, new readonly class($denied) implements MediaPolicy
    {
        public function __construct(private MediaAction $denied) {}

        public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
        {
            return $action !== $this->denied;
        }
    });
    app()->forgetScopedInstances();
}

it('refuses each media operation its consumer policy denies', function (MediaAction $denied, Closure $operation): void {
    allowMedia();
    $stored = resolve(MediaLibrary::class)->store(mediaContext(), upload('request-policy-'.$denied->value, 'guarded bytes'));
    $attached = new AttachmentInput($stored->id, 'crm.contact', 'contact-1', 'avatar');
    resolve(MediaAttachments::class)->attach(mediaContext(), $attached);
    denyMediaAction($denied);

    expect(fn (): mixed => $operation($stored->id))->toThrow(MediaAccessDenied::class)
        ->and(DB::table('med_files')->where('id', $stored->id)->value('state'))->toBe('ready')
        ->and(DB::table('med_relations')->where('file_id', $stored->id)->count())->toBe(1);
})->with([
    'read' => [MediaAction::Read, fn (string $id): mixed => resolve(MediaLibrary::class)->read(mediaContext(), $id)],
    'request conversion' => [MediaAction::Read, fn (string $id): mixed => resolve(MediaLibrary::class)->requestConversion(mediaContext(), $id, 'thumbnail')],
    'usages' => [MediaAction::Read, fn (string $id): mixed => resolve(MediaAttachments::class)->usages(mediaContext(), $id)],
    'attach' => [MediaAction::Attach, fn (string $id): mixed => resolve(MediaAttachments::class)->attach(mediaContext(), new AttachmentInput($id, 'crm.contact', 'contact-2', 'avatar'))],
    'detach' => [MediaAction::Detach, fn (string $id): mixed => resolve(MediaAttachments::class)->detach(mediaContext(), new AttachmentInput($id, 'crm.contact', 'contact-1', 'avatar'))],
    'delete' => [MediaAction::Delete, fn (string $id): mixed => resolve(MediaLibrary::class)->delete(mediaContext(), $id)],
]);

it('refuses to attach to or detach from a record the data scope does not allow writing', function (): void {
    allowMedia();
    $stored = resolve(MediaLibrary::class)->store(mediaContext(), upload('request-record-scope', 'scoped bytes'));
    $foreign = new AttachmentInput($stored->id, 'crm.deal', 'deal-1', 'avatar');

    expect(fn (): mixed => resolve(MediaAttachments::class)->attach(mediaContext(), $foreign))->toThrow(MediaAccessDenied::class)
        ->and(fn (): mixed => resolve(MediaAttachments::class)->detach(mediaContext(), $foreign))->toThrow(MediaAccessDenied::class)
        ->and(DB::table('med_relations')->where('file_id', $stored->id)->count())->toBe(0);
});

it('bounds the usages page size', function (int $limit): void {
    allowMedia();
    $stored = resolve(MediaLibrary::class)->store(mediaContext(), upload('request-usage-limit-'.$limit, 'paged bytes'));

    expect(fn (): mixed => resolve(MediaAttachments::class)->usages(mediaContext(), $stored->id, $limit))->toThrow(MediaOperationConflict::class, 'Media usage limit is invalid.');
})->with([0, 51]);

/** @param Closure(MediaAction, MediaSubject, int): bool $decide */
function decideMedia(Closure $decide): void
{
    app()->instance(MediaPolicy::class, new class($decide) implements MediaPolicy
    {
        private int $calls = 0;

        public function __construct(private readonly Closure $decide) {}

        public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
        {
            return ($this->decide)($action, $subject, ++$this->calls);
        }
    });
    app()->forgetScopedInstances();
}

it('checks the policy both on the request and on the locked file', function (string $operation, bool $denyLocked): void {
    allowMedia();
    $stored = resolve(MediaLibrary::class)->store(mediaContext(), upload("request-stage-{$operation}-".(int) $denyLocked, 'staged bytes'));
    $input = new AttachmentInput($stored->id, 'crm.contact', 'contact-1', 'avatar');

    if ($operation === 'detach') {
        resolve(MediaAttachments::class)->attach(mediaContext(), $input);
    }
    $action = $operation === 'attach' ? MediaAction::Attach : MediaAction::Detach;
    decideMedia(static fn (MediaAction $asked, MediaSubject $subject): bool => $asked !== $action || ($subject->file === null) === $denyLocked);

    expect(fn (): mixed => resolve(MediaAttachments::class)->{$operation}(mediaContext(), $input))->toThrow(MediaAccessDenied::class)
        ->and(DB::table('med_relations')->where('file_id', $stored->id)->count())->toBe($operation === 'detach' ? 1 : 0);
})->with([
    'attach request' => ['attach', false],
    'attach locked file' => ['attach', true],
    'detach request' => ['detach', false],
    'detach locked file' => ['detach', true],
]);

it('rechecks the delete policy after locking the file', function (int $deniedCall): void {
    allowMedia();
    $stored = resolve(MediaLibrary::class)->store(mediaContext(), upload("request-delete-recheck-{$deniedCall}", 'recheck bytes'));
    decideMedia(static fn (MediaAction $action, MediaSubject $subject, int $call): bool => $call !== $deniedCall);

    expect(fn (): mixed => resolve(MediaLibrary::class)->delete(mediaContext(), $stored->id))->toThrow(MediaAccessDenied::class)
        ->and(DB::table('med_files')->where('id', $stored->id)->value('state'))->toBe('ready');
})->with([1, 2]);
