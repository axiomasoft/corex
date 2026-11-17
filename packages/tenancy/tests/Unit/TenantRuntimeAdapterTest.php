<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Tenancy\Runtime\Contracts\AccountIsolationMapper;
use CoreX\Tenancy\Runtime\Internal\TenantRuntimeManager;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Tenant\Composition\CompositionBuilder;
use Tenant\Contracts\Context\ContextResolver;
use Tenant\Data\Context\ContextState;
use Tenant\Data\Context\ExtensionRef;
use Tenant\Data\Context\FacetBindings;
use Tenant\Data\Context\IsolationContext;
use Tenant\Data\Context\IsolationIdentity;
use Tenant\Data\Context\ProfileRef;
use Tenant\Data\Context\Purpose;
use Tenant\Data\Context\ResolvedIsolation;
use Tenant\Data\Context\Selection;
use Tenant\Data\Verification\BindingExpectation;
use Tenant\Exceptions\Runtime\FrameLeak;
use Tenant\Exceptions\Runtime\OpenResource;
use Tenant\Exceptions\Runtime\RuntimePoisoned;
use Tenant\Exceptions\Verification\StaleRoute;
use Tenant\Runtime\SafeTenantRuntime;

/** Real Tenant executor with a trusted no-I/O fixture profile, never production routing. */
function externalTenantRig(): array
{
    $mapper = new class implements AccountIsolationMapper, ContextResolver
    {
        public string $route = 'r1';

        public bool $stale = false;

        public function selection(AccountRef $account, ?WorkspaceRef $workspace = null): Selection
        {
            if (! in_array($account->id, ['A', 'B'], true)) {
                throw new RuntimeException('Untrusted account');
            }

            if ($workspace !== null && $workspace->id !== $account->id.'-workspace') {
                throw new RuntimeException('Workspace belongs to another account');
            }

            return Selection::tenant(new IsolationIdentity('corex-account', $account->id), new ProfileRef('corex-test', 1),
                $workspace === null ? [] : [new ExtensionRef('corex:workspace', 1, false, ['id' => $workspace->id])],
                new BindingExpectation('resource-'.$account->id, 'i1', $this->stale ? 'old-route' : $this->route));
        }

        public function context(ContextState $state): ?TenantContext
        {
            if ($state->kind !== 'active') {
                return null;
            }
            $id = $state->context->identity->id;
            $workspace = $state->context->extensions[0]->refs['id'] ?? null;

            return new TenantContext(new AccountRef($id, $id, 'active', [], []),
                $workspace === null ? null : new WorkspaceRef($workspace, $workspace, 'team', '/'.$workspace, null));
        }

        public function resolve(Selection $selection, Purpose $purpose): ResolvedIsolation
        {
            return new ResolvedIsolation(ContextState::active(new IsolationContext(
                'tenant', $selection->identity, $selection->expectedProfile,
                new BindingExpectation('resource-'.$selection->identity->id, 'i1', $this->route), $selection->extensions,
            )), new FacetBindings([]));
        }
    };
    $runtime = new SafeTenantRuntime($mapper, new CompositionBuilder([
        'corex-test' => ['version' => 1, 'modes' => ['tenant']],
    ])->compile());
    $container = new Container;
    $container->scoped('probe', static fn () => new stdClass);

    return [new TenantRuntimeManager($runtime, $mapper, $container, new Dispatcher($container)), $runtime, $mapper, $container];
}

function externalAccount(string $id): AccountRef
{
    return new AccountRef($id, $id, 'active', [], []);
}

it('delegates nested execution and restores account workspace after callback failure', function (): void {
    [$manager, $runtime] = externalTenantRig();
    $manager->initialize(externalAccount('A'));
    $manager->setWorkspace(new WorkspaceRef('A-workspace', 'w', 'team', '/w', null));
    expect(fn () => $manager->runFor(externalAccount('B'), null, function () use ($manager): void {
        expect($manager->context()->account->id)->toBe('B');

        throw new RuntimeException('application failure');
    }))->toThrow(RuntimeException::class, 'application failure');
    expect($manager->context()->account->id)->toBe('A')
        ->and($manager->context()->workspace->id)->toBe('A-workspace')
        ->and($runtime->current()->context->identity->id)->toBe('A');
    $manager->end();
    expect($manager->context())->toBeNull()->and($runtime->current()->kind)->toBe('missing');
});

it('rejects stale verified route selection before executing application code', function (): void {
    [$manager, $runtime, $mapper] = externalTenantRig();
    $mapper->stale = true;
    $called = false;
    expect(fn () => $manager->runFor(externalAccount('A'), null, function () use (&$called): void {
        $called = true;
    }))
        ->toThrow(StaleRoute::class);
    expect($called)->toBeFalse()->and($runtime->current()->kind)->toBe('missing');
});

it('refuses an untrusted account and foreign workspace through the required host mapper', function (): void {
    [$manager] = externalTenantRig();
    expect(fn () => $manager->initialize(externalAccount('unknown')))->toThrow(RuntimeException::class, 'Untrusted account');
    expect(fn () => $manager->runFor(externalAccount('A'), new WorkspaceRef('B-workspace', 'w', 'team', '/w', null), static fn () => null))
        ->toThrow(RuntimeException::class, 'Workspace belongs to another account');
    expect($manager->initialized())->toBeFalse();
});

it('flushes scoped services at entry restoration and workspace changes', function (): void {
    [$manager, , , $container] = externalTenantRig();
    $initial = $container->make('probe');
    $manager->initialize(externalAccount('A'));
    $account = $container->make('probe');
    expect($account)->not->toBe($initial);
    $manager->setWorkspace(new WorkspaceRef('A-workspace', 'w', 'team', '/w', null));
    expect($container->make('probe'))->not->toBe($account);
    $manager->end();
    expect($container->make('probe'))->not->toBe($account);
});

it('preserves a surrounding frame owned by the host Tenant middleware', function (): void {
    [$manager, $runtime, $mapper] = externalTenantRig();
    $token = $runtime->enter($mapper->selection(externalAccount('A')), new Purpose('test:http'));
    $manager->runFor(externalAccount('B'), null, function () use ($manager): void {
        expect($manager->context()->account->id)->toBe('B');
    });
    $manager->end();
    expect($runtime->current()->context->identity->id)->toBe('A');
    $runtime->restore($token);
    expect($manager->context())->toBeNull();
});

it('lets the upstream executor poison leaked nested frames instead of publishing their context', function (): void {
    [$manager, $runtime, $mapper] = externalTenantRig();
    expect(fn () => $manager->runFor(externalAccount('A'), null, function () use ($runtime, $mapper): void {
        $runtime->enter($mapper->selection(externalAccount('B')), new Purpose('test:leaked'));
    }))->toThrow(FrameLeak::class);
    expect(fn () => $manager->context())->toThrow(RuntimePoisoned::class);
});

it('lets protected upstream execution poison an escaped resource lease', function (): void {
    [$manager, $runtime] = externalTenantRig();
    $release = null;
    expect(function () use ($manager, $runtime, &$release): void {
        $manager->runFor(externalAccount('A'), null, function () use ($runtime, &$release): void {
            $release = $runtime->guard()->acquire();
        });
    })->toThrow(FrameLeak::class);
    expect(fn () => $manager->context())->toThrow(RuntimePoisoned::class);
    $release();
});

it('retains its owned frame when manual restoration refuses an open lease', function (): void {
    [$manager, $runtime] = externalTenantRig();
    $manager->initialize(externalAccount('A'));
    $release = $runtime->guard()->acquire();
    expect(fn () => $manager->end())->toThrow(OpenResource::class);
    expect($manager->context()->account->id)->toBe('A');
    $release();
    $manager->end();
    expect($manager->context())->toBeNull();
});
