<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Identity\Internal\Membership;

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Contracts\CentralApiClient;
use CoreX\Tenancy\Identity\Contracts\MembershipProjection;
use CoreX\Tenancy\Identity\Internal\Models\User;
use CoreX\Tenancy\Membership\Data\MembershipQuery;
use CoreX\Tenancy\Membership\Enums\MembershipState;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\ConnectionResolverInterface;
use RuntimeException;
use Throwable;

/** @internal */
final readonly class ApplyMembershipProjection implements MembershipProjection
{
    public function __construct(
        private string $centralConnection,
        private CentralApiClient $centralApi,
        private TenancyManager $tenancy,
        private AccountRead $accounts,
        private ConnectionResolverInterface $connections,
        private CacheFactory $cache,
    ) {}

    public function refresh(string $identityId, string $accountId, string $product, string $requestId): void
    {
        $account = $this->accounts->find(accountId: $accountId, connectionName: $this->centralConnection);

        if ($account === null) {
            return;
        }

        $key = 'corex:membership:'.hash('sha256', $accountId.'|'.$identityId);
        $accountRef = $account;

        $this->lock($key)->block(seconds: 5, callback: function () use ($accountId, $accountRef, $identityId, $product, $requestId): void {
            try {
                $snapshot = $this->centralApi->membershipFor(new MembershipQuery(
                    identityId: $identityId,
                    accountId: $accountId,
                    product: $product,
                    requestId: $requestId,
                ));
            } catch (Throwable) {
                // A membership response is authoritative only when it is
                // authenticated, available, and matches the exact query. An
                // adapter defect is no authority either: fail closed rather
                // than leave an old active projection usable. Failures while
                // applying this deny are intentionally outside this catch so
                // the queue may retry the local write.
                $this->setActiveState(account: $accountRef, identityId: $identityId, active: false);

                return;
            }

            $this->setActiveState(account: $accountRef, identityId: $identityId, active: $snapshot->state === MembershipState::Active);
        });
    }

    private function lock(string $name): Lock
    {
        $store = $this->cache->store();
        $provider = $store instanceof CacheRepository ? $store->getStore() : null;

        if (! $provider instanceof LockProvider) {
            throw new RuntimeException('The configured cache store does not support atomic locks.');
        }

        return $provider->lock($name, 10);
    }

    private function setActiveState(AccountRef $account, string $identityId, bool $active): void
    {
        $this->tenancy->runFor(account: $account, workspace: null, callback: function () use ($active, $identityId): void {
            User::query()->whereKey($identityId)->update(['is_active' => $active]);

            if (! $active) {
                $this->connections->connection()->table('sessions')->where('user_id', $identityId)->delete();
            }
        });
    }
}
