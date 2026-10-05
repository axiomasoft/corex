<?php

declare(strict_types=1);

use CoreX\Tenancy\Contracts\DomainChallengeStore;
use CoreX\Tenancy\Contracts\DomainOwnershipProofVerifier;
use CoreX\Tenancy\Contracts\DomainTrustAuthorizer;
use CoreX\Tenancy\Contracts\DomainTrustPolicy;
use CoreX\Tenancy\DomainTrust\Data\ChallengeConsumption;
use CoreX\Tenancy\DomainTrust\Data\DomainChallenge;
use CoreX\Tenancy\DomainTrust\Data\DomainChallengeBinding;
use CoreX\Tenancy\DomainTrust\Data\DomainChallengeDraft;
use CoreX\Tenancy\DomainTrust\Data\DomainTrustActor;
use CoreX\Tenancy\DomainTrust\Data\IssuedDomainChallenge;
use CoreX\Tenancy\DomainTrust\Data\NormalizedHost;
use CoreX\Tenancy\DomainTrust\Data\ObservedDomainProof;
use CoreX\Tenancy\DomainTrust\Enums\DomainTrustOperation;
use CoreX\Tenancy\DomainTrust\Events\DomainTrustChanged;
use CoreX\Tenancy\DomainTrust\Exceptions\DomainTrustDenied;
use CoreX\Tenancy\DomainTrust\Exceptions\DomainTrustDisabled;
use CoreX\Tenancy\DomainTrust\Internal\DomainTrustCoordinator;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses()->group('pg');

const ROOT_MIGRATIONS_PATH_DT = __DIR__.'/../../database/migrations/root';

final class FakeDomainTrustPolicyDT implements DomainTrustPolicy
{
    public bool $accepts = true;

    public function normalize(string $host): NormalizedHost
    {
        return new NormalizedHost($host);
    }

    public function accepts(NormalizedHost $host, ObservedDomainProof $proof): bool
    {
        return $this->accepts;
    }
}

final class FakeDomainChallengeStoreDT implements DomainChallengeStore
{
    /** @var array<string, array{account_id: string, generation: int, host: string, token_digest: string}> */
    public array $records = [];

    public function issue(DomainChallengeDraft $draft): IssuedDomainChallenge
    {
        $token = bin2hex(random_bytes(32));
        $this->records[$draft->host->value] = [
            'account_id' => $draft->accountId,
            'generation' => $draft->generation,
            'host' => $draft->host->value,
            'token_digest' => hash_hmac('sha256', $token, 'domain-trust-test-key'),
        ];

        return new IssuedDomainChallenge(
            challenge: new DomainChallenge(
                accountId: $draft->accountId,
                host: $draft->host,
                generation: $draft->generation,
                expiresAt: $draft->expiresAt,
            ),
            token: $token,
        );
    }

    public function consumeMatching(DomainChallengeBinding $binding, ObservedDomainProof $proof): ChallengeConsumption
    {
        $record = $this->records[$binding->host->value] ?? null;

        if ($record === null
            || $record['account_id'] !== $binding->accountId
            || $record['generation'] !== $binding->generation
            || ! is_string($proof->challengeToken)
            || ! hash_equals($record['token_digest'], hash_hmac('sha256', $proof->challengeToken, 'domain-trust-test-key'))) {
            return new ChallengeConsumption(consumed: false);
        }

        unset($this->records[$binding->host->value]);

        return new ChallengeConsumption(consumed: true);
    }
}

final class FakeDomainOwnershipProofVerifierDT implements DomainOwnershipProofVerifier
{
    public ?string $token = null;

    public function observe(NormalizedHost $host): ObservedDomainProof
    {
        return new ObservedDomainProof(facts: ['fake-proof'], challengeToken: $this->token);
    }
}

final class FakeDomainTrustAuthorizerDT implements DomainTrustAuthorizer
{
    public bool $allowed = true;

    /** @var list<DomainTrustOperation> */
    public array $operations = [];

    public function assertAllowed(
        DomainTrustActor $actor,
        DomainTrustOperation $operation,
        string $accountId,
        NormalizedHost $host,
    ): void {
        $this->operations[] = $operation;

        if (! $this->allowed) {
            throw new DomainTrustDenied('The fake authorizer denied the operation.');
        }
    }
}

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH_DT),
        '--realpath' => true,
        '--force' => true,
    ])->run();
});

function domainTrustAccountDT(): string
{
    $clusterId = (string) Str::uuid();
    $identityId = (string) Str::uuid();
    $accountId = (string) Str::uuid();

    DB::table('root_clusters')->insert([
        'id' => $clusterId,
        'name' => "cell-{$clusterId}",
        'pg_host_writer' => 'writer.local',
        'pgbouncer_host' => 'pgbouncer.local',
        'app_db_user' => 'corex',
    ]);
    DB::table('root_identities')->insert([
        'id' => $identityId,
        'email' => "{$identityId}@example.test",
    ]);
    DB::table('root_accounts')->insert([
        'id' => $accountId,
        'cluster_id' => $clusterId,
        'db_name' => 'acc_'.Str::lower(Str::random(8)),
        'slug' => 'account-'.Str::lower(Str::random(8)),
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'owner_identity_id' => $identityId,
        'status' => 'active',
    ]);

    return $accountId;
}

/** @return array{0: DomainTrustCoordinator, 1: FakeDomainChallengeStoreDT, 2: FakeDomainOwnershipProofVerifierDT, 3: FakeDomainTrustAuthorizerDT} */
function domainTrustCoordinatorDT(): array
{
    $policy = new FakeDomainTrustPolicyDT;
    $store = new FakeDomainChallengeStoreDT;
    $verifier = new FakeDomainOwnershipProofVerifierDT;
    $authorizer = new FakeDomainTrustAuthorizerDT;

    config(['corex-tenancy.domain_trust.enabled' => true]);
    app()->forgetInstance(TenancyOptions::class);
    app()->instance(DomainTrustPolicy::class, $policy);
    app()->instance(DomainChallengeStore::class, $store);
    app()->instance(DomainOwnershipProofVerifier::class, $verifier);
    app()->instance(DomainTrustAuthorizer::class, $authorizer);

    return [app(DomainTrustCoordinator::class), $store, $verifier, $authorizer];
}

it('keeps custom-domain verification disabled without consumer port bindings', function (): void {
    expect(fn (): DomainTrustCoordinator => app(DomainTrustCoordinator::class))
        ->toThrow(DomainTrustDisabled::class);
});

it('issues and consumes a proof once, persisting only trusted state and notification metadata', function (): void {
    $accountId = domainTrustAccountDT();
    Event::fake([DomainTrustChanged::class]);
    [$coordinator, $store, $verifier] = domainTrustCoordinatorDT();
    $actor = new DomainTrustActor(id: 'actor-a');

    $issued = $coordinator->issue(actor: $actor, accountId: $accountId, host: 'SHOP.EXAMPLE.TEST');
    $verifier->token = $issued->token;
    $transition = $coordinator->verify(actor: $actor, accountId: $accountId, host: 'shop.example.test');

    expect($transition->newGeneration)->toBe(1)
        ->and(DB::table('root_domains')->where('host', 'shop.example.test')->value('verified_at'))->not->toBeNull()
        ->and($store->records)->toBeEmpty();
    Event::assertDispatched(DomainTrustChanged::class, function (DomainTrustChanged $event) use ($issued): bool {
        return $event->transition->host->value === 'shop.example.test'
            && $event->transition->reason->value === 'verified'
            && ! str_contains(serialize($event), $issued->token);
    });

    expect(fn (): mixed => $coordinator->verify(actor: $actor, accountId: $accountId, host: 'shop.example.test'))
        ->toThrow(DomainTrustDenied::class);
});

it('dispatches the lifecycle notification only after an enclosing transaction commits', function (): void {
    $accountId = domainTrustAccountDT();
    Event::fake([DomainTrustChanged::class]);
    [$coordinator, , $verifier] = domainTrustCoordinatorDT();
    $actor = new DomainTrustActor(id: 'actor-a');

    DB::transaction(function () use ($coordinator, $verifier, $actor, $accountId): void {
        $issued = $coordinator->issue(actor: $actor, accountId: $accountId, host: 'after-commit.example.test');
        $verifier->token = $issued->token;
        $coordinator->verify(actor: $actor, accountId: $accountId, host: 'after-commit.example.test');

        Event::assertNotDispatched(DomainTrustChanged::class);
    });

    Event::assertDispatched(DomainTrustChanged::class);
});

it('denies foreign and stale proof bindings before trusted state changes', function (): void {
    $accountA = domainTrustAccountDT();
    $accountB = domainTrustAccountDT();
    [$coordinator, , $verifier] = domainTrustCoordinatorDT();
    $actor = new DomainTrustActor(id: 'actor-a');

    $issued = $coordinator->issue(actor: $actor, accountId: $accountA, host: 'foreign.example.test');
    $verifier->token = $issued->token;

    expect(fn (): mixed => $coordinator->verify(actor: $actor, accountId: $accountB, host: 'foreign.example.test'))
        ->toThrow(DomainTrustDenied::class);

    DB::table('root_domains')->insert([
        'id' => (string) Str::uuid(),
        'account_id' => $accountA,
        'host' => 'stale.example.test',
        'type' => 'custom',
        'verification_generation' => 1,
    ]);
    $stale = $coordinator->issue(actor: $actor, accountId: $accountA, host: 'stale.example.test');
    $verifier->token = $stale->token;
    DB::table('root_domains')->where('host', 'stale.example.test')->update(['verification_generation' => 2]);

    expect(fn (): mixed => $coordinator->verify(actor: $actor, accountId: $accountA, host: 'stale.example.test'))
        ->toThrow(DomainTrustDenied::class)
        ->and(DB::table('root_domains')->where('host', 'stale.example.test')->value('verified_at'))->toBeNull();
});

it('denies verification and rolls back trusted state when resolver-cache invalidation fails', function (): void {
    $accountId = domainTrustAccountDT();
    [$coordinator, , $verifier] = domainTrustCoordinatorDT();
    $actor = new DomainTrustActor(id: 'actor-a');
    $issued = $coordinator->issue(actor: $actor, accountId: $accountId, host: 'cache-failure.example.test');
    $verifier->token = $issued->token;

    config(['corex-tenancy.host_identification' => [
        'enabled' => true,
        'cache' => true,
        'cache_ttl' => 300,
        'cache_store' => 'domain_trust_failure',
    ]]);
    app()->forgetInstance(TenancyOptions::class);
    app('cache')->extend('domain_trust_failure', static function (): never {
        throw new RuntimeException('The cache store is unavailable.');
    });
    config(['cache.stores.domain_trust_failure' => ['driver' => 'domain_trust_failure']]);

    expect(fn (): mixed => $coordinator->verify(actor: $actor, accountId: $accountId, host: 'cache-failure.example.test'))
        ->toThrow(DomainTrustDenied::class)
        ->and(DB::table('root_domains')->where('host', 'cache-failure.example.test')->exists())->toBeFalse();
});

it('rejects malformed or reserved hosts and an authorizer denial before issuing a challenge', function (): void {
    $accountId = domainTrustAccountDT();
    [$coordinator, $store, , $authorizer] = domainTrustCoordinatorDT();
    $actor = new DomainTrustActor(id: 'actor-a');

    expect(fn (): IssuedDomainChallenge => $coordinator->issue(actor: $actor, accountId: $accountId, host: 'localhost'))
        ->toThrow(InvalidArgumentException::class);

    $authorizer->allowed = false;

    expect(fn (): IssuedDomainChallenge => $coordinator->issue(actor: $actor, accountId: $accountId, host: 'denied.example.test'))
        ->toThrow(DomainTrustDenied::class)
        ->and($store->records)->toBeEmpty();
});

it('clears trust and increments generation on revoke and reassignment', function (): void {
    $accountA = domainTrustAccountDT();
    $accountB = domainTrustAccountDT();
    [$coordinator, , $verifier, $authorizer] = domainTrustCoordinatorDT();
    $actor = new DomainTrustActor(id: 'actor-a');

    $issued = $coordinator->issue(actor: $actor, accountId: $accountA, host: 'move.example.test');
    $verifier->token = $issued->token;
    $coordinator->verify(actor: $actor, accountId: $accountA, host: 'move.example.test');
    $revoked = $coordinator->revoke(actor: $actor, accountId: $accountA, host: 'move.example.test');
    $reassigned = $coordinator->reassign(
        actor: $actor,
        fromAccountId: $accountA,
        toAccountId: $accountB,
        host: 'move.example.test',
    );

    expect($revoked->newGeneration)->toBe(2)
        ->and($reassigned->newGeneration)->toBe(3)
        ->and(DB::table('root_domains')->where('host', 'move.example.test')->first(['account_id', 'verified_at', 'verification_generation']))
        ->toMatchObject([
            'account_id' => $accountB,
            'verified_at' => null,
            'verification_generation' => 3,
        ])
        ->and($authorizer->operations)->toContain(DomainTrustOperation::Reassign);
});
