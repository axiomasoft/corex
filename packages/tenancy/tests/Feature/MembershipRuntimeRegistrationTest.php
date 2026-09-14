<?php

declare(strict_types=1);

use CoreX\Tenancy\Actions\ApplyMembershipProjection;
use CoreX\Tenancy\Contracts\CentralApiClient;
use CoreX\Tenancy\Http\Middleware\RequireActiveMembership;
use CoreX\Tenancy\Membership\Internal\Projection\ReferenceMembershipProjection;
use Illuminate\Routing\Router;

it('does not register unfinished membership runtime consumers', function (): void {
    /** @var Router $router */
    $router = app(Router::class);

    expect(app()->bound(ApplyMembershipProjection::class))->toBeFalse()
        ->and(app()->bound(ReferenceMembershipProjection::class))->toBeFalse()
        ->and(app()->bound(CentralApiClient::class))->toBeFalse()
        ->and($router->getMiddleware())->not->toHaveKey('tenancy.membership')
        ->and($router->getMiddlewareGroups()['tenant'] ?? [])->not->toContain(RequireActiveMembership::class)
        ->and(config('tenancy.membership_guard'))->toBeNull();
});
