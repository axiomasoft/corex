<?php

declare(strict_types=1);

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\LifecycleInventory;
use CoreX\Foundation\Contracts\LifecyclePlans;
use CoreX\Foundation\Data\ModuleId;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Enums\LifecycleAction;
use CoreX\Foundation\Enums\LifecyclePhase;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Files\Contracts\MediaLibrary;
use CoreX\Media\Internal\MediaModuleReferences;
use CoreX\Media\Laravel\UploadedFileSource;
use CoreX\Media\Tests\PgTestCase;
use CoreX\Tests\Support\LocalWorkContexts;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Enums\Lifecycle\DisableReason;
use Packman\Modules\Enums\Lifecycle\ModuleState;
use Vaulter\Media\Contracts\Cas\BlobPins;

abstract class MediaModulePurgeCase extends PgTestCase
{
    // Native enable must run and claim Media's schema migrations in this installation.
    protected function defineDatabaseMigrations(): void
    {
        if (! $this->app->environment('testing') || ! str_ends_with(DB::connection()->getDatabaseName(), '_test')) {
            throw new RuntimeException('Expected the private Media test database.');
        }
        DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
        DB::statement('CREATE SCHEMA public');
        $this->artisan('migrate', ['--database' => 'pgsql', '--force' => true])->assertSuccessful();
        $this->migrateSchemaSets(['axiomasoft/corex:tenant', 'axiomasoft/packman-modules:tenant'], 'pgsql');
    }
}

final class OtherMediaInventoryModule extends ModuleDefinition
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('fixture/unrelated')->title('Unrelated');
    }
}

uses(MediaModulePurgeCase::class)->group('pg');

it('accounts for exactly the native Media table drop between purge replay and repair', function (): void {
    $media = 'axiomasoft/corex-media';
    $other = 'fixture/unrelated';
    $base = app()->basePath();
    $site = sys_get_temp_dir().'/corex-media-purge-'.bin2hex(random_bytes(10));
    $files = new Filesystem;
    $files->makeDirectory($site.'/vendor/composer', 0700, true);
    $files->makeDirectory($site.'/bootstrap/cache', 0700, true);
    $this->beforeApplicationDestroyed(function () use ($base, $files, $site): void {
        app()->setBasePath($base);
        $files->deleteDirectory($site);
    });
    $metadata = json_decode($files->get(dirname(__DIR__, 4).'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
    $metadata['packages'] = array_values(array_filter($metadata['packages'], static fn (array $p): bool => in_array($p['name'], ['axiomasoft/corex', 'axiomasoft/packman-modules', $media], true)));
    $metadata['packages'][] = ['name' => $other, 'version' => '1.0.0', 'type' => 'packman-module', 'extra' => ['packman' => ['module' => OtherMediaInventoryModule::class]]];
    $files->put($site.'/vendor/composer/installed.json', json_encode($metadata, JSON_THROW_ON_ERROR));
    app()->setBasePath($site);
    app()->forgetInstance(ModuleRegistry::class);
    $this->artisan('modules:compile')->assertSuccessful();

    $accountRef = app(TenantContextResolver::class)->current()->account;
    $contexts = LocalWorkContexts::install(app());
    $contexts->initialize($accountRef);
    $host = WorkScopeFixture::install(app());
    $scope = $host->scope();
    $contexts->setWorkspace(new WorkspaceRef('W1', 'one', 'workspace', '/', null));
    $workspace = $host->scope();
    $contexts->setWorkspace(null);
    $inspectMedia = true;
    foreach (['enable', 'inspect', 'disable', 'purge', 'repair'] as $ability) {
        Gate::define('corex.lifecycle.'.$ability, static function (ScopePrincipal $principal, DataScope $current, ModuleRef $target) use ($scope, $ability, $other, $media, &$inspectMedia): bool {
            return $current == $scope && $principal->kind === $scope->principalKind && $principal->id === $scope->principalId
                && ($target->package === $other || ($target->package === $media && ($ability !== 'inspect' || $inspectMedia)));
        });
    }
    app()->instance(LifecycleInventory::class, new readonly class($scope, $workspace, $contexts) implements LifecycleInventory
    {
        public function __construct(private DataScope $account, private DataScope $workspace, private LocalWorkContexts $contexts) {}

        public function scopes(DataScope $operator, ModuleRef $target): array
        {
            return [$this->workspace, $this->account];
        }

        public function owners(DataScope $scope, ModuleRef $target): array
        {
            return [];
        }

        public function revision(DataScope $operator, ModuleRef $target): string
        {
            return 'media-account-and-W1';
        }

        public function run(DataScope $scope, Closure $operation): mixed
        {
            return $this->contexts->runFor($this->contexts->account($scope->accountId), $scope->workspaceId === null ? null : $this->contexts->workspace($scope->workspaceId), static function () use ($scope, $operation): mixed {
                app(CurrentWorkScope::class)->assert($scope, DB::connection());

                return $operation();
            });
        }
    });
    app()->instance(MediaPolicy::class, new class implements MediaPolicy
    {
        public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
        {
            return $context->actorId === 'actor-1';
        }
    });
    $native = app(ModuleLifecycle::class);
    $plans = app(LifecyclePlans::class);
    $native->enable($other);
    // Before Media has ever been enabled, the same absent schema has no purge provenance.
    $planOther = fn () => $plans->plan($scope, new ModuleId($other), LifecycleAction::Disable);
    $first = $planOther();
    $missing = $plans->inspect($scope, $first->ref)->blockers;
    expect(array_column($missing, 'owner'))->toBe([$media, $media])
        ->and(array_column(array_column($missing, 'scope'), 'workspaceId'))->toBe(['W1', null]);
    $native->enable($media);
    $library = app(MediaLibrary::class);
    $context = new MediaContext(app(TenantContextResolver::class)->current(), 'user', 'actor-1');
    $file = $library->store($context, new UploadInput('original-upload', UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('receipt.txt', 'original bytes')), 'attachment'));
    expect(app(BlobPins::class)->countByOwner())->toBe(['corex.media.file' => 1]);
    $native->disable($media, DisableReason::Manual);
    $purge = $plans->plan($scope, new ModuleId($media), LifecycleAction::Purge);
    $done = $plans->apply($scope, $purge->ref, $purge->digest);
    $tables = ['med_files', 'med_relations', 'med_operations', 'med_conversions'];
    expect($done->phase)->toBe(LifecyclePhase::Done)
        ->and($native->status($media)->state)->toBe(ModuleState::Purged)
        ->and($done->receipts)->toHaveCount(1)
        ->and($done->receipts[0]->action->resourceId)->toBe($file->id)
        ->and(json_decode($done->receipts[0]->evidence, true, flags: JSON_THROW_ON_ERROR)['request'])->toBe('original-upload')
        ->and(app(BlobPins::class)->countByOwner())->toBe([])
        ->and($plans->apply($scope, $purge->ref, $purge->digest)->receipts)->toEqual($done->receipts);
    foreach ($tables as $table) {
        expect(DB::connection()->getSchemaBuilder()->hasTable($table))->toBeFalse();
    }

    DB::statement('create table med_operations (id bigint primary key)');
    $partial = $planOther();
    expect(array_column($plans->inspect($scope, $partial->ref)->blockers, 'owner'))->toContain($media);
    DB::statement('drop table med_operations');
    // An incomplete restore of Core's certificate cannot excuse the missing Media schema.
    $journal = DB::table('sys_lifecycle_plans')->where('plan_id', $purge->ref->id);
    $originalReceipts = $journal->sole()->receipts;
    $journal->update(['receipts' => '[]']);

    try {
        $incomplete = $planOther();
        expect(array_column($plans->inspect($scope, $incomplete->ref)->blockers, 'owner'))->toContain($media);
    } finally {
        $journal->update(['receipts' => $originalReceipts]);
    }
    $inspectMedia = false;
    expect(fn () => $native->status($media))->toThrow(AuthorizationException::class)
        ->and(fn () => $plans->inspect($scope, $purge->ref))->toThrow(AuthorizationException::class)
        ->and(fn () => app(MediaModuleReferences::class)->scan(new ModuleRef($other), $scope, null))->toThrow(LifecycleBlocked::class)
        ->and($native->status($other)->state)->toBe(ModuleState::Enabled);
    $clear = $planOther();
    $scan = $plans->inspect($scope, $clear->ref);
    expect($scan->blockers)->toBe([])
        ->and($plans->apply($scope, $clear->ref, $clear->digest)->phase)->toBe(LifecyclePhase::Done)
        ->and($native->status($other)->state)->toBe(ModuleState::Disabled)
        ->and(fn () => $native->status($media))->toThrow(AuthorizationException::class);
    $inspectMedia = true;
    expect($plans->inspect($scope, $purge->ref)->receipts)->toEqual($done->receipts);

    $native->repair($media);
    expect($native->status($media)->state)->toBe(ModuleState::Available);
    $inspectMedia = false;
    $afterRepair = $plans->plan($scope, new ModuleId($other), LifecycleAction::Purge);
    expect(array_column($plans->inspect($scope, $afterRepair->ref)->blockers, 'owner'))->toContain($media);
    $inspectMedia = true;
    $native->enable($media);
    expect($native->status($media)->state)->toBe(ModuleState::Enabled)
        ->and(DB::table('med_files')->count())->toBe(0);
});
