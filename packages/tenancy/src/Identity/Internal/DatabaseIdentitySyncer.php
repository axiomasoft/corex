<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Identity\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\IdentityPasswordRehasher;
use CoreX\Context\Contracts\NamedWorkPrincipal;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Identity\Contracts\IdentitySyncer;
use CoreX\Tenancy\Identity\Events\UserSynced;
use CoreX\Tenancy\Identity\Internal\Jobs\SyncIdentityToAccount;
use CoreX\Tenancy\Identity\Internal\Models\User;
use DateTimeImmutable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Reads `root_identities` (central) and writes the denormalized `users`
 * projection inside the target account's tenant database (B-11 §2.2/§3.2,
 * D4/C6). A version-guard (premortem C3/C6) skips the write once the
 * account's projection is already at least as fresh as the source, so two
 * concurrent {@see fanOut()} waves converge on the latest state instead of
 * an older job clobbering a newer one. Any failure — missing identity,
 * missing account, a tenant-context error — is swallowed into
 * `sync_status=failed` (AC-16 г): retried later by `corex:tenancy:reconcile-identities`,
 * never left to throw across the whole fan-out.
 */
#[Internal]
final readonly class DatabaseIdentitySyncer implements IdentityPasswordRehasher, IdentitySyncer
{
    public const string SERVICE = 'corex.identity-sync';

    /** @param Closure():Container $application */
    public function __construct(
        private string $centralConnection,
        private TenancyManager $tenancy,
        private AccountRead $accounts,
        private ConnectionResolverInterface $connections,
        private ClockInterface $clock,
        private Dispatcher $events,
        private Closure $application,
    ) {}

    /** @param array<string, string> $columns */
    public function rehash(string $identityId, string $expectedHash, string $replacementHash, string $table, array $columns, ?string $connection): void
    {
        if ($table !== 'users' || ($columns['id'] ?? null) !== 'id' || ($columns['password'] ?? null) !== 'password_hash'
            || ($columns['active'] ?? null) !== 'is_active' || ($columns['deleted_at'] ?? null) !== 'deleted_at') {
            throw new InvalidArgumentException('The tenancy identity writer only owns the canonical users credential mapping.');
        }

        $query = User::on($connection)
            ->whereKey($identityId)
            ->where('password_hash', $expectedHash)
            ->where('is_active', true);
        $query->getQuery()->whereNull('deleted_at');
        $query->update(['password_hash' => $replacementHash]);
    }

    public function syncToAccount(string $identityId, string $accountId): void
    {
        try {
            $this->asService($accountId, function (DataScope $scope) use ($identityId): void {
                ($this->application)()->make(WorkScopeRunner::class)->run($scope, fn () => $this->syncCaptured($identityId, $scope));
            });
        } catch (Throwable) {
            $this->markFailed($identityId, $accountId);
        }
    }

    public function syncCaptured(string $identityId, DataScope $scope): void
    {
        $accountId = $scope->accountId;

        if ($scope->workspaceId !== null || $scope->principalKind !== 'service' || $scope->principalId !== self::SERVICE) {
            throw new ScopeContextMismatch('A granted identity synchronization principal is required.');
        }
        $current = ($this->application)()->make(CurrentWorkScope::class);
        $current->assert($scope);
        $identity = $this->connections->connection($this->centralConnection)
            ->table('root_identities')
            ->where('id', $identityId)
            ->first();

        if ($identity === null) {
            $this->markFailed($identityId, $accountId);

            return;
        }
        $membership = $this->connections->connection($this->centralConnection)
            ->table('root_identity_accounts')
            ->where('identity_id', $identityId)
            ->where('account_id', $accountId)
            ->first();

        /** @var Connection $connection */
        $connection = $this->connections->connection();
        $connection->transaction(function () use ($connection, $identity, $identityId, $accountId, $scope, $current): void {
            $current->assert($scope, $connection);
            // The lock also covers an absent user row, serializing first delivery.
            $connection->select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['corex-identity:'.$identityId]);
            $existing = User::withTrashed()->whereKey($identityId)->first();
            $sourceUpdatedAt = new DateTimeImmutable(DatabaseValue::text($identity->updated_at));
            $status = $this->connections->connection($this->centralConnection)->table('root_identity_accounts')
                ->where('identity_id', $identityId)->where('account_id', $accountId)->value('status');

            if ($status === null && $existing === null) {
                return;
            }
            $isMembershipActive = $status === 'active';

            if ($existing !== null
                && $existing->identity_source_updated_at !== null
                && $sourceUpdatedAt <= $existing->identity_source_updated_at) {
                // Membership authority is independent of the profile clock. A
                // revoke must apply even when the identity profile has not
                // changed; a stale profile snapshot must still not overwrite
                // local profile data.
                User::query()
                    ->whereKey($identity->id)
                    ->update(['is_active' => $isMembershipActive]);

                return;
            }

            User::withTrashed()->updateOrCreate(
                attributes: ['id' => $identity->id],
                values: [
                    'email' => $identity->email,
                    'name' => $identity->name,
                    'phone' => $identity->phone,
                    'locale' => $identity->locale,
                    'is_active' => $isMembershipActive,
                    'synced_at' => $this->clock->now(),
                    'identity_source_updated_at' => $sourceUpdatedAt->format('Y-m-d H:i:s.uP'),
                ],
            );

            $connection->afterCommit(fn () => $this->events->dispatch(new UserSynced(identityId: $identityId, accountId: $accountId)));
        });

        if ($membership !== null) {
            $this->connections->connection($this->centralConnection)
                ->table('root_identity_accounts')
                ->where('identity_id', $identityId)
                ->where('account_id', $accountId)
                ->where('updated_at', $membership->updated_at)
                ->whereExists(static fn (Builder $query) => $query->selectRaw('1')->from('root_identities')
                    ->where('id', $identityId)->where('updated_at', $identity->updated_at))
                ->update(['sync_status' => 'synced', 'synced_at' => $this->clock->now()]);
        }
    }

    public function fanOut(string $identityId): void
    {
        $accountIds = $this->connections->connection($this->centralConnection)
            ->table('root_identity_accounts')
            ->where('identity_id', $identityId)
            ->pluck('account_id');

        foreach ($accountIds as $accountId) {
            $id = DatabaseValue::text($accountId);

            try {
                $this->asService($id, static function (DataScope $scope) use ($identityId): void {
                    SyncIdentityToAccount::dispatch($identityId, $scope);
                });
            } catch (Throwable) {
                $this->markFailed($identityId, $id);
            }
        }
    }

    /** @param Closure(DataScope):void $work */
    private function asService(string $accountId, Closure $work): void
    {
        $account = $this->accounts->find($accountId, $this->centralConnection) ?? throw new ScopeContextMismatch('Account is unavailable.');
        $this->tenancy->runFor($account, null, function () use ($work): void {
            $context = $this->tenancy->context() ?? throw new ScopeContextMismatch('Missing identity projection target.');
            $app = ($this->application)();
            $app->make(NamedWorkPrincipal::class)->run($context, self::SERVICE, static function () use ($context, $work, $app): void {
                $scope = $app->make(DataScopeFactory::class)->forCurrentPrincipal($context);

                if ($scope->principalKind !== 'service' || $scope->principalId !== self::SERVICE) {
                    throw new ScopeContextMismatch('Missing identity service grant.');
                }
                $work($scope);
            });
        });
    }

    private function markFailed(string $identityId, string $accountId): void
    {
        $this->connections->connection($this->centralConnection)
            ->table('root_identity_accounts')
            ->where('identity_id', $identityId)
            ->where('account_id', $accountId)
            ->update(['sync_status' => 'failed']);
    }
}
