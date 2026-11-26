<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Contracts\Foundation\Application;

const RESIDENT_TENANT_A = '0198a000-0000-7000-8000-00000000000a';
const RESIDENT_TENANT_B = '0198a000-0000-7000-8000-00000000000b';

/**
 * Resident worker model: one application, many requests. The lifecycle boundary
 * between two requests is Application::forgetScopedInstances() (what Octane and
 * queue workers run); bindings that hold tenant state must be scoped, so a
 * service resolved for tenant A can never be handed to tenant B.
 */
function residentEnterTenant(Application $app, string $accountId): void
{
    $app->instance(TenantContextResolver::class, new readonly class($accountId) implements TenantContextResolver
    {
        public function __construct(private string $accountId) {}

        public function current(): TenantContext
        {
            return new TenantContext(
                account: new AccountRef(id: $this->accountId, slug: $this->accountId, status: 'active', features: [], limits: []),
            );
        }
    });
}

it('does not carry scoped services or tenant context across a lifecycle boundary', function (): void {
    $options = $this->app->make(CoreOptions::class);

    residentEnterTenant($this->app, RESIDENT_TENANT_A);
    $first = [
        Outbox::class => $this->app->make(Outbox::class),
        Inbox::class => $this->app->make(Inbox::class),
        TenantStorage::class => $this->app->make(TenantStorage::class),
        WorkScopeRunner::class => $this->app->make(WorkScopeRunner::class),
    ];

    $this->app->forgetScopedInstances();
    residentEnterTenant($this->app, RESIDENT_TENANT_B);

    // Compare object ids, not objects: a failing identity diff would export the whole
    // container (incl. the process environment) into logs that end up in the repo.
    foreach ($first as $abstract => $instance) {
        expect(spl_object_id($this->app->make($abstract)))->not->toBe(spl_object_id($instance), $abstract.' survived the lifecycle boundary');
    }

    expect($this->app->make(TenantContextResolver::class)->current()->account->id)->toBe(RESIDENT_TENANT_B)
        ->and($this->app->make(CoreOptions::class))->toBe($options);
});

it('keeps scoped services stable inside one lifecycle', function (): void {
    residentEnterTenant($this->app, RESIDENT_TENANT_A);

    expect(spl_object_id($this->app->make(Outbox::class)))->toBe(spl_object_id($this->app->make(Outbox::class)))
        ->and(spl_object_id($this->app->make(TenantStorage::class)))->toBe(spl_object_id($this->app->make(TenantStorage::class)));
});

it('builds tenant storage for the tenant that is current after the boundary', function (): void {
    residentEnterTenant($this->app, RESIDENT_TENANT_A);
    $rootA = $this->app->make(TenantStorage::class)->disk()->path('');

    $this->app->forgetScopedInstances();
    residentEnterTenant($this->app, RESIDENT_TENANT_B);
    $rootB = $this->app->make(TenantStorage::class)->disk()->path('');

    expect($rootA)->toContain(RESIDENT_TENANT_A)->not->toContain(RESIDENT_TENANT_B)
        ->and($rootB)->toContain(RESIDENT_TENANT_B)->not->toContain(RESIDENT_TENANT_A);
});
