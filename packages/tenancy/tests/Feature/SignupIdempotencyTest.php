<?php

declare(strict_types=1);

use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Contracts\TemplateIntegrityVerifier;
use CoreX\Tenancy\Provisioning\Data\ClaimRequest;
use CoreX\Tenancy\Provisioning\Data\QualifiedTemplate;
use CoreX\Tenancy\Provisioning\Exceptions\ClaimConflict;
use CoreX\Tenancy\Provisioning\Exceptions\ClaimUnavailable;
use CoreX\Tenancy\Provisioning\Internal\ClaimAccountSignup;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');
});

afterEach(function (): void {
    DB::purge('signup_one');
    DB::purge('signup_two');
});

function signupConnection(string $name): Connection
{
    config(["database.connections.{$name}" => [
        'driver' => 'pgsql',
        'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
        'port' => getenv('PGSQL_PORT') ?: '5434',
        'database' => getenv('PGSQL_DATABASE') ?: 'corex_test',
        'username' => getenv('PGSQL_USERNAME') ?: 'corex',
        'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
        'charset' => 'utf8',
        'prefix' => '',
        'schema' => 'public',
    ]]);

    DB::purge($name);

    return DB::connection($name);
}

function signupFixture(): array
{
    $clusterId = (string) Str::uuid();
    $templateId = (string) Str::uuid();

    DB::table('root_clusters')->insert([
        'id' => $clusterId,
        'name' => "signup-{$clusterId}",
        'status' => 'active',
        'pg_host_writer' => 'writer.local',
        'pgbouncer_host' => 'pgbouncer.local',
        'app_db_user' => 'corex',
        'template_version' => 1,
    ]);

    DB::table('root_templates')->insert([
        'id' => $templateId,
        'version' => 1,
        'db_name' => 'signup_template',
        'status' => 'ready',
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
    ]);

    return [$clusterId, $templateId];
}

function pendingSignupAccount(string $clusterId, array $signup = []): string
{
    $accountId = (string) Str::uuid();

    $attributes = [
        'id' => $accountId,
        'cluster_id' => $clusterId,
        'db_name' => 'acc_'.str_replace('-', '', $accountId),
        'status' => 'pending',
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
    ];

    $attributes = array_merge($attributes, $signup);

    DB::table('root_accounts')->insert($attributes);

    return $accountId;
}

function qualifiedSignupTemplate(string $clusterId, string $templateId): TemplateIntegrityVerifier
{
    return new readonly class($clusterId, $templateId) implements TemplateIntegrityVerifier
    {
        public function __construct(
            private string $clusterId,
            private string $templateId,
        ) {}

        public function qualify(ClaimRequest $request): QualifiedTemplate
        {
            return new QualifiedTemplate(
                clusterId: $this->clusterId,
                templateId: $this->templateId,
                templateVersion: 1,
                artifactDigest: str_repeat('a', 64),
            );
        }
    };
}

function unavailableSignupTemplate(): TemplateIntegrityVerifier
{
    return new class implements TemplateIntegrityVerifier
    {
        public function qualify(ClaimRequest $request): QualifiedTemplate
        {
            throw new ClaimUnavailable('Template proof is unavailable.');
        }
    };
}

function signupRequest(string $key, string $clusterId, string $templateId, ?string $digest = null): ClaimRequest
{
    return new ClaimRequest(
        requestKey: $key,
        clusterId: $clusterId,
        templateId: $templateId,
        requestDigest: $digest ?? hash('sha256', "{$clusterId}:{$templateId}"),
    );
}

function signupClaim(string $connection, TemplateIntegrityVerifier $verifier): ClaimAccountSignup
{
    signupConnection($connection);

    return new ClaimAccountSignup(
        connections: resolve(ConnectionResolverInterface::class),
        centralConnection: $connection,
        templateIntegrity: $verifier,
    );
}

it('returns one reservation when two independent connections claim the same key', function (): void {
    [$clusterId, $templateId] = signupFixture();
    $accountId = pendingSignupAccount($clusterId);
    $request = signupRequest('checkout:0001', $clusterId, $templateId);
    $verifier = qualifiedSignupTemplate($clusterId, $templateId);

    $first = signupClaim('signup_one', $verifier)->claim($request);
    $second = signupClaim('signup_two', $verifier)->claim($request);

    expect($first->accountId)->toBe($accountId)
        ->and($first->replayed)->toBeFalse()
        ->and($second->accountId)->toBe($accountId)
        ->and($second->replayed)->toBeTrue()
        ->and(DB::table('root_accounts')->where('status', 'provisioning')->count())->toBe(1);

    $row = DB::table('root_accounts')->where('id', $accountId)->first();
    expect((array) $row)->toMatchArray([
        'signup_key' => 'checkout:0001',
        'signup_request_digest' => $request->requestDigest,
        'signup_cluster_id' => $clusterId,
        'signup_template_id' => $templateId,
    ]);
});

it('reserves different accounts for different keys', function (): void {
    [$clusterId, $templateId] = signupFixture();
    pendingSignupAccount($clusterId);
    pendingSignupAccount($clusterId);
    $verifier = qualifiedSignupTemplate($clusterId, $templateId);

    $first = signupClaim('signup_one', $verifier)->claim(signupRequest('checkout:0001', $clusterId, $templateId));
    $second = signupClaim('signup_two', $verifier)->claim(signupRequest('checkout:0002', $clusterId, $templateId));

    expect($first->accountId)->not->toBe($second->accountId)
        ->and($first->replayed)->toBeFalse()
        ->and($second->replayed)->toBeFalse();
});

it('rejects a replay with incompatible immutable input without reserving another account', function (string $changed): void {
    [$clusterId, $templateId] = signupFixture();
    $accountId = pendingSignupAccount($clusterId);
    $verifier = qualifiedSignupTemplate($clusterId, $templateId);
    $claim = signupClaim('signup_one', $verifier);

    $claim->claim(signupRequest('checkout:0001', $clusterId, $templateId));

    expect(fn () => $claim->claim(signupRequest(
        'checkout:0001',
        $changed === 'cluster' ? (string) Str::uuid() : $clusterId,
        $changed === 'template' ? (string) Str::uuid() : $templateId,
        $changed === 'digest' ? str_repeat('b', 64) : hash('sha256', "{$clusterId}:{$templateId}"),
    )))
        ->toThrow(ClaimConflict::class);

    expect(DB::table('root_accounts')->where('id', $accountId)->value('status'))->toBe('provisioning')
        ->and(DB::table('root_accounts')->where('status', 'pending')->count())->toBe(0);
})->with(['digest', 'cluster', 'template']);

it('rejects partial signup writes through the named all-or-nothing check', function (): void {
    [$clusterId] = signupFixture();
    $accountId = pendingSignupAccount($clusterId);

    try {
        DB::table('root_accounts')->where('id', $accountId)->update(['signup_key' => 'partial']);

        throw new RuntimeException('The signup check accepted a partial binding.');
    } catch (QueryException $exception) {
        expect($exception->getPrevious()->getCode())->toBe('23514')
            ->and($exception->getMessage())->toContain('root_accounts_signup_ck');
    }
    expect(DB::table('root_accounts')->where('id', $accountId)->value('signup_key'))->toBeNull();
});

it('rejects a second live signup binding but permits the schema slot after soft deletion', function (): void {
    [$clusterId, $templateId] = signupFixture();
    $signup = [
        'signup_key' => 'duplicate',
        'signup_request_digest' => str_repeat('a', 64),
        'signup_cluster_id' => $clusterId,
        'signup_template_id' => $templateId,
    ];
    $first = pendingSignupAccount($clusterId, $signup);

    try {
        pendingSignupAccount($clusterId, $signup);

        throw new RuntimeException('The signup index accepted a duplicate live binding.');
    } catch (QueryException $exception) {
        expect($exception->getPrevious()->getCode())->toBe('23505')
            ->and($exception->getMessage())->toContain('root_accounts_signup_key_uq');
    }
    DB::table('root_accounts')->where('id', $first)->update(['deleted_at' => now()]);
    expect(pendingSignupAccount($clusterId, $signup))->not->toBe($first);
});

it('keeps signup and cleanup columns outside account mass assignment', function (): void {
    $account = new Account;

    foreach (['signup_key', 'signup_request_digest', 'signup_cluster_id', 'signup_template_id', 'pool_cleanup'] as $field) {
        expect($account->isFillable($field))->toBeFalse();
    }
});

it('fails closed when template integrity is missing before the first write', function (): void {
    [$clusterId, $templateId] = signupFixture();
    $accountId = pendingSignupAccount($clusterId);

    expect(fn () => signupClaim('signup_one', unavailableSignupTemplate())
        ->claim(signupRequest('checkout:0001', $clusterId, $templateId)))
        ->toThrow(ClaimUnavailable::class);

    expect(DB::table('root_accounts')->where('id', $accountId)->value('status'))->toBe('pending')
        ->and(DB::table('root_accounts')->where('id', $accountId)->value('signup_key'))->toBeNull();
});

it('rejects empty keys and soft-deleted key bindings', function (): void {
    [$clusterId, $templateId] = signupFixture();
    $accountId = pendingSignupAccount($clusterId, [
        'signup_key' => 'checkout:0001',
        'signup_request_digest' => hash('sha256', "{$clusterId}:{$templateId}"),
        'signup_cluster_id' => $clusterId,
        'signup_template_id' => $templateId,
    ]);
    DB::table('root_accounts')->where('id', $accountId)->update([
        'status' => 'provisioning',
        'deleted_at' => now(),
    ]);
    pendingSignupAccount($clusterId);
    $claim = signupClaim('signup_one', qualifiedSignupTemplate($clusterId, $templateId));

    expect(fn () => $claim->claim(signupRequest('', $clusterId, $templateId)))
        ->toThrow(ClaimConflict::class);
    expect(fn () => $claim->claim(signupRequest('checkout:0001', $clusterId, $templateId)))
        ->toThrow(ClaimConflict::class);

    expect(DB::table('root_accounts')->where('status', 'pending')->count())->toBe(1);
});
