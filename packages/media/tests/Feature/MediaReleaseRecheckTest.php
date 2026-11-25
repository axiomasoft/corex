<?php

declare(strict_types=1);

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\CleanupAction;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Media\Attachments\Contracts\MediaAttachments;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Data\AttachmentInput;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaFileRef;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Files\Contracts\MediaLibrary;
use CoreX\Media\Internal\MediaModuleReferences;
use CoreX\Media\Laravel\UploadedFileSource;
use CoreX\Tests\Support\ConfirmedCleanup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** @return array{MediaContext, MediaFileRef, DataScope, CleanupAction} */
function plannedMediaRelease(): array
{
    app()->instance(MediaPolicy::class, new class implements MediaPolicy
    {
        public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
        {
            return true;
        }
    });
    app()->forgetScopedInstances();
    $context = new MediaContext(resolve(TenantContextResolver::class)->current(), 'user', 'actor-1');
    $file = resolve(MediaLibrary::class)->store($context, new UploadInput('planned-release', UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('test.txt', 'bytes')), 'attachment'));
    $scope = resolve(DataScopeFactory::class)->forCurrentPrincipal($context->tenant);
    $action = collect(resolve(MediaModuleReferences::class)->scan(new ModuleRef('axiomasoft/corex-media'), $scope, null)->actions)->firstWhere('resourceId', $file->id);
    assert($action instanceof CleanupAction);

    return [$context, $file, $scope, $action];
}

it('rechecks under lock for work that arrived after the purge scan', function (Closure $arrive): void {
    [$context, $file, $scope, $action] = plannedMediaRelease();
    $arrive($context, $file->id);
    $probe = resolve(MediaModuleReferences::class);

    expect(fn () => ConfirmedCleanup::run($scope, $action, fn () => $probe->cleanup($scope, $action)))
        ->toThrow(LifecycleBlocked::class, 'Unresolved Media attachment, conversion or release.')
        ->and(DB::table('med_files')->where('id', $file->id)->value('state'))->toBe('ready')
        ->and(DB::table('med_files')->where('id', $file->id)->value('blob_id'))->not->toBeNull();
})->with([
    'an attachment' => static fn (MediaContext $context, string $id): mixed => resolve(MediaAttachments::class)->attach($context, new AttachmentInput($id, 'crm.contact', 'contact-1', 'avatar')),
    'a queued conversion' => static fn (MediaContext $context, string $id): mixed => resolve(MediaLibrary::class)->requestConversion($context, $id, 'thumbnail'),
]);

it('refuses to release inside a caller transaction and releases on its own', function (): void {
    [, $file, $scope, $action] = plannedMediaRelease();
    $probe = resolve(MediaModuleReferences::class);
    $state = static fn (): mixed => DB::table('med_files')->where('id', $file->id)->value('state');

    expect(fn () => ConfirmedCleanup::run($scope, $action, fn () => DB::transaction(fn () => $probe->cleanup($scope, $action))))
        ->toThrow(LifecycleBlocked::class, 'Independent Media release transaction required.')
        ->and($state())->toBe('ready')
        ->and(fn () => $probe->cleanup($scope, $action))->toThrow(LifecycleBlocked::class, 'Owner cleanup requires an active confirmed plan.')
        ->and(ConfirmedCleanup::run($scope, $action, fn () => $probe->cleanup($scope, $action))->action->resourceId)->toBe($file->id)
        ->and($state())->toBe('deleted');
});
