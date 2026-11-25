<?php

declare(strict_types=1);

use CoreX\Audit\Enums\ActorType;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Access\Exceptions\TasksModuleDisabled;
use CoreX\Tasks\Errors\Enums\TasksErrorCode;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\EntityReference;
use CoreX\Tasks\Task\Data\MoveTaskToStageData;
use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Internal\TaskModuleReferences;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Packman\Errors\Enums\ErrorCategory;

it('fails closed when the host binds no task authorizer', function (): void {
    $this->app->forgetInstance(TaskAccess::class);
    $access = resolve(TaskAccess::class);
    $context = resolve(TenantContextResolver::class)->current();
    $actor = new ActorData(ActorType::User, (string) Str::uuid7());
    $workspaceId = (string) Str::uuid7();

    expect(fn () => $access->authorizeCreate(new TaskDraft($workspaceId, 'Draft', $actor), $context))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $access->authorizeMove(new MoveTaskToStageData((string) Str::uuid7(), (string) Str::uuid7(), $actor), $context))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $access->authorizeTarget(new EntityReference('crm.deal', (string) Str::uuid7()), $actor, $context))
        ->toThrow(AuthorizationException::class);
});

it('reports a disabled module as a non-retryable unavailable error', function (): void {
    $descriptor = TasksModuleDisabled::forCurrentAccount()->errorDescriptor();

    expect($descriptor->code)->toBe(TasksErrorCode::TasksModuleDisabled->value)
        ->and($descriptor->category)->toBe(ErrorCategory::Unavailable)
        ->and($descriptor->retryable)->toBeFalse();
});

it('inventories every table the package migrations create', function (): void {
    $created = [];
    foreach (glob(dirname(__DIR__, 2).'/database/migrations/*/*.php') ?: [] as $migration) {
        preg_match_all("~->create\\('([a-z_]+)'~", (string) file_get_contents($migration), $matches);
        array_push($created, ...$matches[1]);
    }
    sort($created);

    $references = collect(app()->tagged(ModuleReferences::class))
        ->first(static fn (object $owner): bool => $owner instanceof TaskModuleReferences);
    $declared = $references->moduleTables();
    sort($declared);

    expect($references->owner())->toBe('axiomasoft/corex-tasks')
        ->and($created)->not->toBeEmpty()
        ->and($declared)->toBe($created);
});

it('scans every declared table against the live schema without blockers', function (): void {
    $page = resolve(TaskModuleReferences::class)->scan(new ModuleRef('axiomasoft/corex-tasks'), $this->workScope, null);

    expect($page->blockers)->toBe([])
        ->and($page->revision)->toMatch('/\A[0-9a-f]{64}\z/');
});
