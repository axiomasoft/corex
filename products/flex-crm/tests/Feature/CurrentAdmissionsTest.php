<?php

declare(strict_types=1);

use Flex\Crm\Company\Actions\CreateCompany;
use Flex\Crm\Company\Actions\UpdateCompany;
use Flex\Crm\Company\Data\CompanyData;
use Flex\Crm\Context\ContextGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

function crmPrincipalReads(Closure $work): int
{
    $reads = 0;
    DB::listen(static function (QueryExecuted $query) use (&$reads): void {
        if (str_contains($query->sql, '"users"') || str_contains($query->sql, '"wsp_workspace')) {
            $reads++;
        }
    });
    $work();

    return $reads;
}

function crmDeactivatePrincipal(Connection $db, string $id): void
{
    $db->table('users')->where('id', $id)->update(['is_active' => false]);
}

it('checks current workspace admission in one query', function (): void {
    $ctx = $this->crmContext->current();
    $guard = resolve(ContextGuard::class);

    expect(crmPrincipalReads(static fn () => $guard->user(ctx: $ctx, id: $ctx->principalId, lock: true)))->toBe(1);
});

it('refuses a company write after the same transaction withdraws admission', function (Closure $withdraw): void {
    $ctx = $this->crmContext->current();
    $guard = resolve(ContextGuard::class);
    $company = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Original', ownerId: $ctx->principalId));
    $db = $guard->connection($ctx);

    $db->transaction(function () use ($guard, $ctx, $db, $withdraw, $company): void {
        $guard->user(ctx: $ctx, id: $ctx->principalId, lock: true);
        $withdraw($db, $ctx->principalId, $ctx->tenant->workspace->id);

        expect(fn () => resolve(UpdateCompany::class)->handle(companyId: $company->id, changes: ['name' => 'Revoked']))->toThrow(AuthorizationException::class)
            ->and($db->table('crm_companies')->where('id', $company->id)->value('name'))->toBe('Original');
    });
})->with([
    'deactivated user' => [static fn (Connection $db, string $user, string $workspace) => crmDeactivatePrincipal($db, $user)],
    'revoked membership' => [static fn (Connection $db, string $user, string $workspace) => $db->table('wsp_workspace_members')->where('user_id', $user)->where('workspace_id', $workspace)->delete()],
    'removed workspace' => [static fn (Connection $db, string $user, string $workspace) => $db->table('wsp_workspaces')->where('id', $workspace)->update(['deleted_at' => now()])],
]);

it('checks the current admission after a savepoint rollback', function (): void {
    $ctx = $this->crmContext->current();
    $guard = resolve(ContextGuard::class);
    $db = $guard->connection($ctx);

    $db->transaction(function () use ($guard, $ctx, $db): void {
        try {
            $db->transaction(static function () use ($guard, $ctx): never {
                $guard->user(ctx: $ctx, id: $ctx->principalId, lock: true);

                throw new RuntimeException('roll the savepoint back');
            });
        } catch (RuntimeException) {
        }
        crmDeactivatePrincipal($db, $ctx->principalId);

        expect(fn () => $guard->user(ctx: $ctx, id: $ctx->principalId, lock: true))->toThrow(AuthorizationException::class);
    });
});

it('checks current admission in the next transaction', function (): void {
    $ctx = $this->crmContext->current();
    $guard = resolve(ContextGuard::class);
    $db = $guard->connection($ctx);

    $db->transaction(static fn () => $guard->user(ctx: $ctx, id: $ctx->principalId, lock: true));
    crmDeactivatePrincipal($db, $ctx->principalId);

    expect(fn () => $db->transaction(static fn () => $guard->user(ctx: $ctx, id: $ctx->principalId, lock: true)))->toThrow(AuthorizationException::class);
});

it('checks current admission on the next worker request', function (): void {
    $ctx = $this->crmContext->current();
    $db = resolve(ContextGuard::class)->connection($ctx);

    $db->transaction(function () use ($ctx, $db): void {
        resolve(ContextGuard::class)->user(ctx: $ctx, id: $ctx->principalId, lock: true);

        // What Octane and the queue worker do between requests and jobs.
        app()->forgetScopedInstances();
        crmDeactivatePrincipal($db, $ctx->principalId);

        expect(fn () => resolve(ContextGuard::class)->user(ctx: $ctx, id: $ctx->principalId, lock: true))->toThrow(AuthorizationException::class);
    });
});

it('rechecks an earlier unlocked admission at the write boundary', function (): void {
    $ctx = $this->crmContext->current();
    $guard = resolve(ContextGuard::class);
    $db = $guard->connection($ctx);

    $db->transaction(function () use ($guard, $ctx, $db): void {
        $guard->user(ctx: $ctx, id: $ctx->principalId);
        crmDeactivatePrincipal($db, $ctx->principalId);

        expect(fn () => $guard->user(ctx: $ctx, id: $ctx->principalId, lock: true))->toThrow(AuthorizationException::class);
    });
});
