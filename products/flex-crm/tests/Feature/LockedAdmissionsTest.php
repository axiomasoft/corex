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

it('reads the principal rows once per save transaction instead of on every check', function (): void {
    $principal = $this->crmContext->context->principalId;
    $company = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Original', ownerId: $principal));

    // The unlocked pre-check before the transaction and the first locked check
    // inside it read users, workspace and membership; the locked re-check, the
    // tenant scope, both saving observers and the owner check reuse that proof.
    // Before: 7 rounds of 3 reads.
    expect(crmPrincipalReads(static fn () => resolve(UpdateCompany::class)->handle(companyId: $company->id, changes: ['name' => 'Renamed'])))->toBe(6);
});

it('forgets a proof when the savepoint that took the locks rolls back', function (): void {
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

it('does not carry a proof into the next transaction', function (): void {
    $ctx = $this->crmContext->current();
    $guard = resolve(ContextGuard::class);
    $db = $guard->connection($ctx);

    $db->transaction(static fn () => $guard->user(ctx: $ctx, id: $ctx->principalId, lock: true));
    crmDeactivatePrincipal($db, $ctx->principalId);

    expect(fn () => $db->transaction(static fn () => $guard->user(ctx: $ctx, id: $ctx->principalId, lock: true)))->toThrow(AuthorizationException::class);
});

it('does not share proofs across requests on the same worker', function (): void {
    $ctx = $this->crmContext->current();
    $db = resolve(ContextGuard::class)->connection($ctx);

    $db->transaction(function () use ($ctx, $db): void {
        resolve(ContextGuard::class)->user(ctx: $ctx, id: $ctx->principalId, lock: true);
        expect(crmPrincipalReads(static fn () => resolve(ContextGuard::class)->user(ctx: $ctx, id: $ctx->principalId, lock: true)))->toBe(0);

        // What Octane and the queue worker do between requests and jobs.
        app()->forgetScopedInstances();
        crmDeactivatePrincipal($db, $ctx->principalId);

        expect(fn () => resolve(ContextGuard::class)->user(ctx: $ctx, id: $ctx->principalId, lock: true))->toThrow(AuthorizationException::class);
    });
});

it('never trusts an unlocked check as a proof', function (): void {
    $ctx = $this->crmContext->current();
    $guard = resolve(ContextGuard::class);
    $db = $guard->connection($ctx);

    $db->transaction(function () use ($guard, $ctx, $db): void {
        $guard->user(ctx: $ctx, id: $ctx->principalId);
        crmDeactivatePrincipal($db, $ctx->principalId);

        expect(fn () => $guard->user(ctx: $ctx, id: $ctx->principalId, lock: true))->toThrow(AuthorizationException::class);
    });
});
