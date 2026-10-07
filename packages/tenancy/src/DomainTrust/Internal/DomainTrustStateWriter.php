<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Internal;

use Carbon\CarbonImmutable;
use CoreX\Tenancy\Contracts\DomainChallengeStore;
use CoreX\Tenancy\DomainTrust\Data\DomainChallengeBinding;
use CoreX\Tenancy\DomainTrust\Data\DomainTrustTransition;
use CoreX\Tenancy\DomainTrust\Data\NormalizedHost;
use CoreX\Tenancy\DomainTrust\Data\ObservedDomainProof;
use CoreX\Tenancy\DomainTrust\Enums\DomainTrustChangeReason;
use CoreX\Tenancy\DomainTrust\Events\DomainTrustChanged;
use CoreX\Tenancy\DomainTrust\Exceptions\DomainTrustDenied;
use CoreX\Tenancy\DomainTrust\Internal\Models\Domain;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;
use Throwable;

final class DomainTrustStateWriter
{
    public function __construct(
        private readonly ConnectionResolverInterface $connections,
        private readonly ClockInterface $clock,
        private readonly Dispatcher $events,
        private readonly string $centralConnection,
    ) {}

    public function generationFor(string $accountId, NormalizedHost $host): int
    {
        $query = $this->domains();
        $query->getQuery()->whereRaw(sql: 'lower(host) = ?', bindings: [$host->value])
            ->whereNull(columns: 'deleted_at');
        $domain = $query->first();

        if ($domain === null) {
            return 0;
        }

        if ($domain->account_id !== $accountId) {
            throw new DomainTrustDenied('The host belongs to another account.');
        }

        return $domain->verification_generation;
    }

    public function verify(string $accountId, NormalizedHost $host, DomainChallengeStore $challengeStore, ObservedDomainProof $proof): DomainTrustTransition
    {
        try {
            $transition = $this->connection()->transaction(callback: function () use ($accountId, $host, $challengeStore, $proof): DomainTrustTransition {
                $domain = $this->lockedDomain($host);

                if ($domain !== null && $domain->account_id !== $accountId) {
                    throw new DomainTrustDenied('The host belongs to another account.');
                }

                if ($domain !== null && $domain->verified_at !== null) {
                    throw new DomainTrustDenied('The host is already trusted.');
                }

                $oldGeneration = $domain === null ? 0 : $domain->verification_generation;
                $consumption = $challengeStore->consumeMatching(
                    binding: new DomainChallengeBinding(accountId: $accountId, host: $host, generation: $oldGeneration),
                    proof: $proof,
                );

                if (! $consumption->consumed) {
                    throw new DomainTrustDenied('The ownership proof was rejected.');
                }

                $occurredAt = $this->clock->now();
                $domain ??= new Domain([
                    'id' => (string) Str::uuid7(),
                    'account_id' => $accountId,
                    'host' => $host->value,
                    'type' => 'custom',
                    'is_primary' => false,
                    'tls_status' => 'none',
                ]);
                $domain->setConnection($this->centralConnection);
                $domain->verified_at = CarbonImmutable::instance($occurredAt);
                $domain->verification_generation = $oldGeneration + 1;
                $this->saveDomain($domain);

                return new DomainTrustTransition(
                    accountId: $accountId,
                    host: $host,
                    oldGeneration: $oldGeneration,
                    newGeneration: $domain->verification_generation,
                    reason: DomainTrustChangeReason::Verified,
                    occurredAt: $occurredAt,
                );
            });
        } catch (QueryException $exception) {
            throw new DomainTrustDenied(message: 'The host could not be claimed.', previous: $exception);
        }

        $this->dispatchChange($transition);

        return $transition;
    }

    public function revoke(string $accountId, NormalizedHost $host): DomainTrustTransition
    {
        $transition = $this->connection()->transaction(callback: function () use ($accountId, $host): DomainTrustTransition {
            $domain = $this->lockedDomain($host);

            if ($domain === null || $domain->account_id !== $accountId) {
                throw new DomainTrustDenied('The host is not owned by this account.');
            }

            $occurredAt = $this->clock->now();
            $oldGeneration = $domain->verification_generation;
            $domain->verified_at = null;
            $domain->verification_generation = $oldGeneration + 1;
            $this->saveDomain($domain);

            return new DomainTrustTransition(
                accountId: $accountId,
                host: $host,
                oldGeneration: $oldGeneration,
                newGeneration: $domain->verification_generation,
                reason: DomainTrustChangeReason::Revoked,
                occurredAt: $occurredAt,
            );
        });

        $this->dispatchChange($transition);

        return $transition;
    }

    public function reassign(string $fromAccountId, string $toAccountId, NormalizedHost $host): DomainTrustTransition
    {
        $transition = $this->connection()->transaction(callback: function () use ($fromAccountId, $toAccountId, $host): DomainTrustTransition {
            $domain = $this->lockedDomain($host);

            if ($domain === null || $domain->account_id !== $fromAccountId) {
                throw new DomainTrustDenied('The host is not owned by the source account.');
            }

            $occurredAt = $this->clock->now();
            $oldGeneration = $domain->verification_generation;
            $domain->verified_at = null;
            $domain->account_id = $toAccountId;
            $domain->verification_generation = $oldGeneration + 1;
            $this->saveDomain($domain);

            return new DomainTrustTransition(
                accountId: $toAccountId,
                host: $host,
                oldGeneration: $oldGeneration,
                newGeneration: $domain->verification_generation,
                reason: DomainTrustChangeReason::Reassigned,
                occurredAt: $occurredAt,
            );
        });

        $this->dispatchChange($transition);

        return $transition;
    }

    private function connection(): Connection
    {
        /** @var Connection */
        $connection = $this->connections->connection(name: $this->centralConnection);

        return $connection;
    }

    /** @return Builder<Domain> */
    private function domains(): Builder
    {
        return Domain::on(connection: $this->centralConnection);
    }

    private function lockedDomain(NormalizedHost $host): ?Domain
    {
        $query = $this->domains();
        $query->getQuery()->whereRaw(sql: 'lower(host) = ?', bindings: [$host->value])
            ->whereNull(columns: 'deleted_at')
            ->lockForUpdate();

        return $query->first();
    }

    private function dispatchChange(DomainTrustTransition $transition): void
    {
        $this->connection()->afterCommit(
            callback: fn (): mixed => $this->events->dispatch(new DomainTrustChanged(transition: $transition)),
        );
    }

    private function saveDomain(Domain $domain): void
    {
        try {
            $domain->save();
        } catch (Throwable $exception) {
            throw new DomainTrustDenied(message: 'Trusted state could not be made current.', previous: $exception);
        }
    }
}
