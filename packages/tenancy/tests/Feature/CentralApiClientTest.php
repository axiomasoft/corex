<?php

declare(strict_types=1);

use CoreX\Tenancy\Membership\Data\ActorRef;
use CoreX\Tenancy\Membership\Data\MembershipQuery;
use CoreX\Tenancy\Membership\Data\RevokeMembershipRequest;
use CoreX\Tenancy\Membership\Enums\ActorType;
use CoreX\Tenancy\Membership\Enums\MembershipReason;
use CoreX\Tenancy\Membership\Events\MembershipRevoked;
use CoreX\Tenancy\Membership\Exceptions\CentralProtocolViolation;
use CoreX\Tenancy\Membership\Exceptions\CentralUnauthorized;
use CoreX\Tenancy\Membership\Exceptions\CentralUnavailable;
use CoreX\Tenancy\Membership\Exceptions\RevokeUncertain;
use CoreX\Tenancy\Membership\Internal\Transport\AccessTokenValidator;
use CoreX\Tenancy\Membership\Internal\Transport\StrictTransport;
use CoreX\Tenancy\Tests\Support\Membership\FakeCentral;
use CoreX\Tenancy\Tests\Support\Membership\TransportFixture;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('keeps membership event payloads UUID-only and reason-enumerated', function (): void {
    $event = new MembershipRevoked(
        identityId: (string) Str::uuid(),
        accountId: (string) Str::uuid(),
        membershipId: (string) Str::uuid(),
        reason: MembershipReason::Policy,
        actor: new ActorRef(type: ActorType::System, id: (string) Str::uuid()),
        occurredAt: new DateTimeImmutable('2026-09-13T00:00:00+00:00'),
    );

    expect(get_object_vars($event))->toHaveKeys(['identityId', 'accountId', 'membershipId', 'reason', 'actor', 'occurredAt'])
        ->and(get_object_vars($event))->not->toHaveKeys(['email', 'phone', 'name']);
});

// D8 local-reference tests. Existing public client tests above remain unchanged.
it('reference transport emits exact scalar GET bytes and mutual TLS options', function (): void {
    $fixture = new TransportFixture;

    try {
        Http::preventStrayRequests();
        Http::fake(function (Request $request, array $options) use ($fixture) {
            expect($options['cert'])->toBe($fixture->binding->certificatePath)->and($options['ssl_key'])->toBe($fixture->binding->keyPath)
                ->and($options['verify'])->toBe($fixture->binding->caPath)->and($options['allow_redirects'])->toBeFalse()->and($options['timeout'])->toBe(5);

            return Http::response($fixture->snapshot(), headers: $fixture::headers());
        });
        $client = $fixture->client();
        $snapshot = $client->membershipFor($fixture->query());
        expect($snapshot->state->value)->toBe('active')->and($snapshot->revision)->toBe('10')->and($snapshot->revokeGeneration)->toBe('3');
        Http::assertSent(function (Request $request) use ($fixture): bool {
            expect($request->url())->toBe('https://central.example.test/reference/state?identity_id='.$fixture::IDENTITY.'&account_id='.$fixture::ACCOUNT.'&product=reference%3Aapp&request_id='.$fixture::REQUEST)
                ->and($request->body())->toBe('')->and($request->method())->toBe('GET')
                ->and($request->hasHeader('Cache-Control', 'no-store'))->toBeTrue()
                ->and($request->hasHeader('X-CoreX-Membership-Profile', 'corex-membership/1'))->toBeTrue();

            return true;
        });
        expect(fn () => $client->membershipFor($fixture->query()))->toThrow(CentralProtocolViolation::class);
        Http::assertSentCount(1);
    } finally {
        $fixture->cleanup();
    }
});

it('reference channel rejects certificate token and context mismatches before HTTP', function (string $case): void {
    $fixture = new TransportFixture;

    try {
        Http::fake();
        $claims = $fixture->accessClaims();
        $binding = $fixture->binding;
        switch ($case) {
            case 'account': $claims['account_id'] = $fixture::OTHER;

                break;
            case 'product': $claims['product'] = 'other';

                break;
            case 'client': $claims['client_id'] = 'other';

                break;
            case 'subject': $claims['sub'] = $fixture::IDENTITY;

                break;
            case 'issuer': $claims['iss'] = 'https://other.example.test';

                break;
            case 'audience': $claims['aud'] = 'https://other.example.test';

                break;
            case 'no-cnf': unset($claims['cnf']);

                break;
            case 'wrong-cnf': $claims['cnf'] = ['x5t#S256' => 'wrong'];

                break;
            case 'no-capability': $claims['scope'] = 'membership.revoke';

                break;
            case 'expired': $claims['iat'] = $fixture->now - 400;
                $claims['exp'] = $fixture->now - 31;

                break;
            case 'long-ttl': $claims['exp'] = $fixture->now + 301;

                break;
            case 'future': $claims['iat'] = $fixture->now + 31;
                $claims['exp'] = $fixture->now + 300;

                break;
            case 'nonintegral': $claims['iat'] = (string) $fixture->now;

                break;
            case 'registry-account': $binding = $fixture->binding(['accountId' => $fixture::OTHER]);

                break;
            case 'registry-product': $binding = $fixture->binding(['product' => 'other']);

                break;
            case 'retired': $binding = $fixture->binding(['certificateAcceptedUntil' => $fixture->now]);

                break;
            case 'fingerprint': $binding = $fixture->binding(['certificateThumbprint' => 'wrong']);

                break;
            case 'insecure': $binding = $fixture->binding(['readUri' => 'http://central.example.test/state']);

                break;
            case 'query-uri': $binding = $fixture->binding(['readUri' => 'https://central.example.test/state?account=other']);

                break;
            case 'missing-key': $binding = $fixture->binding(['keyPath' => $fixture->directory.'/absent']);

                break;
        }
        $client = $fixture->client(claims: $claims, binding: $binding, contextAccount: $case === 'context-account' ? $fixture::OTHER : $fixture::ACCOUNT, contextProduct: $case === 'context-product' ? 'other' : $fixture::PRODUCT);
        expect(fn () => $client->membershipFor($fixture->query()))->toThrow(in_array($case, ['insecure', 'query-uri', 'missing-key'], true) ? CentralUnavailable::class : CentralUnauthorized::class);
        Http::assertNothingSent();
    } finally {
        $fixture->cleanup();
    }
})->with(['account', 'product', 'client', 'subject', 'issuer', 'audience', 'no-cnf', 'wrong-cnf', 'no-capability', 'expired', 'long-ttl', 'future', 'nonintegral', 'registry-account', 'registry-product', 'retired', 'fingerprint', 'insecure', 'query-uri', 'missing-key', 'context-account', 'context-product']);

it('reference response rejects wrong echo malformed fields and cached status', function (string $case): void {
    $fixture = new TransportFixture;

    try {
        $payload = $fixture->snapshot();
        $headers = $fixture::headers();
        $status = 200;
        switch ($case) {
            case 'identity_id': case 'account_id': case 'request_id': $payload[$case] = $fixture::OTHER;

                break;
            case 'product': $payload['product'] = 'other';

                break;
            case 'profile_version': $payload['profile_version'] = 'other/1';

                break;
            case 'missing-null': unset($payload['membership_id']);

                break;
            case 'active-null': $payload['membership_id'] = null;

                break;
            case 'state': $payload['state'] = 'unknown';

                break;
            case 'counter-type': $payload['revision'] = 10;

                break;
            case 'counter-overflow': $payload['revision'] = '18446744073709551616';

                break;
            case 'counter-canonical': $payload['revoke_generation'] = '03';

                break;
            case 'date': $payload['read_started_at'] = '2026-02-30T00:00:00Z';

                break;
            case 'timezone': $payload['observed_at'] = '2026-09-13T00:00:00+02:00';

                break;
            case 'extra': $payload['email'] = 'pii@example.test';

                break;
            case 'no-store': unset($headers['Cache-Control']);

                break;
            case 'profile-header': $headers['X-CoreX-Membership-Profile'] = 'other';

                break;
            case 'age': $headers['Age'] = '0';

                break;
            case 'etag': $headers['ETag'] = 'saved';

                break;
            case 'media-type': $headers['Content-Type'] = 'application/jsonp';

                break;
            case '304': $status = 304;

                break;
            case 'redirect': $status = 302;

                break;
            case '404': $status = 404;

                break;
        }
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        if ($case === 'duplicate') {
            $body = str_replace('"revision":"10"', '"revision":"9","revision":"10"', $body);
        }
        Http::fake(['*' => Http::response($body, $status, $headers)]);
        expect(fn () => $fixture->client()->membershipFor($fixture->query()))->toThrow(CentralProtocolViolation::class);
        Http::assertSentCount(1);
    } finally {
        $fixture->cleanup();
    }
})->with(['identity_id', 'account_id', 'request_id', 'product', 'profile_version', 'missing-null', 'active-null', 'state', 'counter-type', 'counter-overflow', 'counter-canonical', 'date', 'timezone', 'extra', 'no-store', 'profile-header', 'age', 'etag', 'media-type', '304', 'redirect', '404', 'duplicate']);

it('reference snapshots retain nonactive tombstone counters and reject history regression', function (string $state): void {
    $fixture = new TransportFixture;

    try {
        $first = [...$fixture->snapshot(), 'membership_id' => null, 'state' => $state, 'revision' => '9007199254740993', 'revoke_generation' => '9223372036854775808'];
        $next = [...$first, 'request_id' => $fixture::OTHER, 'revision' => '9007199254740992'];
        Http::fakeSequence()->push($first, headers: $fixture::headers())->push($next, headers: $fixture::headers());
        $client = $fixture->client();
        $snapshot = $client->membershipFor($fixture->query());
        expect($snapshot->state->value)->toBe($state)->and($snapshot->revokeGeneration)->toBe('9223372036854775808');
        expect(fn () => $client->membershipFor($fixture->query($fixture::OTHER)))->toThrow(CentralProtocolViolation::class);
    } finally {
        $fixture->cleanup();
    }
})->with(['invited', 'pending', 'blocked', 'revoked', 'deleted', 'missing']);

it('reference revoke requires actual permission and preserves uncertain logical operation', function (): void {
    $fixture = new TransportFixture;

    try {
        Http::fakeSequence()->pushFailedConnection()->push($fixture->receipt(), headers: $fixture::headers());
        $request = new RevokeMembershipRequest(requestId: $fixture::REQUEST, identityId: $fixture::IDENTITY, accountId: $fixture::ACCOUNT, product: $fixture::PRODUCT, reason: MembershipReason::Removed, actingIdentityId: $fixture::OTHER);
        expect(fn () => $fixture->client(permission: false)->revokeMembership($request))->toThrow(CentralUnauthorized::class);
        Http::assertNothingSent();
        $client = $fixture->client();
        expect(fn () => $client->revokeMembership($request))->toThrow(RevokeUncertain::class);
        Http::assertSentCount(1); // No automatic changed-ID retry.
        $receipt = $client->revokeMembership($request);
        expect($receipt->requestId)->toBe($request->requestId)->and($receipt->commitRef)->toBe('commit:fixture-10');
        Http::assertSent(fn (Request $sent): bool => $sent->method() === 'POST' && $sent->data() === $request->payload());
        $conflict = new RevokeMembershipRequest(requestId: $fixture::REQUEST, identityId: $fixture::IDENTITY, accountId: $fixture::ACCOUNT, product: $fixture::PRODUCT, reason: MembershipReason::Policy, actingIdentityId: $fixture::OTHER);
        expect(fn () => $client->revokeMembership($conflict))->toThrow(CentralProtocolViolation::class);
        Http::assertSentCount(2);
    } finally {
        $fixture->cleanup();
    }
});

it('reference maps read and revoke HTTP failures to distinct typed outcomes', function (int $status): void {
    $fixture = new TransportFixture;

    try {
        Http::fake(['*' => Http::response(status: $status)]);
        $readFailure = in_array($status, [401, 403], true) ? CentralUnauthorized::class : ($status === 409 ? CentralProtocolViolation::class : CentralUnavailable::class);
        $revokeFailure = in_array($status, [401, 403], true) ? CentralUnauthorized::class : ($status === 409 ? CentralProtocolViolation::class : RevokeUncertain::class);
        $client = $fixture->client();
        expect(fn () => $client->membershipFor($fixture->query()))->toThrow($readFailure);
        $request = new RevokeMembershipRequest(requestId: $fixture::REQUEST, identityId: $fixture::IDENTITY, accountId: $fixture::ACCOUNT, product: $fixture::PRODUCT, reason: MembershipReason::Removed, actingIdentityId: null);
        expect(fn () => $client->revokeMembership($request))->toThrow($revokeFailure);
    } finally {
        $fixture->cleanup();
    }
})->with([401, 403, 409, 429, 500, 503]);

it('reference central fixture parses GET and replays one revoke receipt with audit-bound payload', function (): void {
    $fixture = new TransportFixture;

    try {
        $central = new FakeCentral($fixture);
        Http::fake(fn (Request $request) => $central($request));
        $client = $fixture->client();
        $request = new RevokeMembershipRequest(requestId: $fixture::REQUEST, identityId: $fixture::IDENTITY, accountId: $fixture::ACCOUNT, product: $fixture::PRODUCT, reason: MembershipReason::Removed, actingIdentityId: null);
        $first = $client->revokeMembership($request);
        $second = $client->revokeMembership($request);
        expect(get_object_vars($second))->toEqual(get_object_vars($first))->and($central->transitions)->toBe(1);
        $snapshot = $client->membershipFor($fixture->query($fixture::OTHER));
        expect($snapshot->state->value)->toBe('revoked');
        $changedActor = new RevokeMembershipRequest(requestId: $fixture::REQUEST, identityId: $fixture::IDENTITY, accountId: $fixture::ACCOUNT, product: $fixture::PRODUCT, reason: MembershipReason::Removed, actingIdentityId: $fixture::OTHER);
        expect(fn () => $client->revokeMembership($changedActor))->toThrow(CentralProtocolViolation::class);
        expect($central->transitions)->toBe(1);
        Http::assertSentCount(3);
    } finally {
        $fixture->cleanup();
    }
});

it('reference has no call without channel binding or qualified deduplication', function (bool $missingBinding): void {
    $fixture = new TransportFixture;

    try {
        Http::fake();
        $client = new StrictTransport(http: app(HttpFactory::class), binding: $missingBinding ? null : $fixture->binding(['deduplicationQualified' => false]), accessToken: $fixture->sign($fixture->accessClaims()), tokenValidator: new AccessTokenValidator($fixture->verifier), clock: fn (): int => $fixture->now, context: fn (): array => ['accountId' => $fixture::ACCOUNT, 'product' => $fixture::PRODUCT], removalPermission: fn (): bool => true);
        $request = new RevokeMembershipRequest(requestId: $fixture::REQUEST, identityId: $fixture::IDENTITY, accountId: $fixture::ACCOUNT, product: $fixture::PRODUCT, reason: MembershipReason::Removed, actingIdentityId: null);
        expect(fn () => $client->revokeMembership($request))->toThrow(CentralUnavailable::class);
        Http::assertNothingSent();
    } finally {
        $fixture->cleanup();
    }
})->with([true, false]);

it('reference rechecks certificate retirement on each request', function (): void {
    $fixture = new TransportFixture;

    try {
        Http::fake(['*' => Http::response($fixture->snapshot(), headers: $fixture::headers())]);
        $client = $fixture->client();
        $client->membershipFor($fixture->query());
        $fixture->now += 600;
        expect(fn () => $client->membershipFor($fixture->query($fixture::OTHER)))->toThrow(CentralUnauthorized::class);
        Http::assertSentCount(1);
    } finally {
        $fixture->cleanup();
    }
});

it('reference old signed grant only triggers a pull of current revoked state', function (): void {
    $fixture = new TransportFixture;

    try {
        $central = new FakeCentral($fixture);
        Http::fake(fn (Request $request) => $central($request));
        $trigger = $fixture->parser()->parse(compact: $fixture->sign($fixture->eventClaims(), event: true), now: $fixture->now);
        $query = new MembershipQuery(identityId: $trigger->identityId, accountId: $trigger->accountId, product: $trigger->product, requestId: $fixture::OTHER);
        $snapshot = $fixture->client()->membershipFor($query);
        expect($trigger->kind->value)->toBe('identity.membership.granted')->and($snapshot->state->value)->toBe('revoked')
            ->and($central->transitions)->toBe(0);
        Http::assertSentCount(1);
    } finally {
        $fixture->cleanup();
    }
});

it('reference rejects malformed revoke receipt without local success', function (string $case): void {
    $fixture = new TransportFixture;

    try {
        $receipt = $fixture->receipt();
        $status = 200;

        if (in_array($case, ['request_id', 'identity_id', 'account_id'], true)) {
            $receipt[$case] = $fixture::OTHER;
        } elseif ($case === 'product') {
            $receipt['product'] = 'other';
        } elseif ($case === 'missing') {
            unset($receipt['commit_ref']);
        } elseif ($case === 'generation') {
            $receipt['revoke_generation'] = 3;
        } elseif ($case === '204') {
            $status = 204;
        } elseif ($case === 'commit') {
            $receipt['commit_ref'] = 'private free text';
        }
        Http::fake(['*' => Http::response($receipt, $status, $fixture::headers())]);
        $request = new RevokeMembershipRequest(requestId: $fixture::REQUEST, identityId: $fixture::IDENTITY, accountId: $fixture::ACCOUNT, product: $fixture::PRODUCT, reason: MembershipReason::Removed, actingIdentityId: null);
        expect(fn () => $fixture->client()->revokeMembership($request))->toThrow(CentralProtocolViolation::class);
    } finally {
        $fixture->cleanup();
    }
})->with(['request_id', 'identity_id', 'account_id', 'product', 'missing', 'generation', '204', 'commit']);

it('reference rejects generation regression and equal revision conflicts', function (string $case): void {
    $fixture = new TransportFixture;

    try {
        $next = [...$fixture->snapshot($fixture::OTHER), ...match ($case) {
            'generation' => ['revision' => '11', 'revoke_generation' => '2'],
            'equal-generation' => ['revoke_generation' => '4'],
            'equal-state' => ['state' => 'blocked'],
            'equal-membership' => ['membership_id' => $fixture::OTHER],
        }];
        Http::fakeSequence()->push($fixture->snapshot(), headers: $fixture::headers())->push($next, headers: $fixture::headers());
        $client = $fixture->client();
        $client->membershipFor($fixture->query());
        expect(fn () => $client->membershipFor($fixture->query($fixture::OTHER)))->toThrow(CentralProtocolViolation::class);
    } finally {
        $fixture->cleanup();
    }
})->with(['generation', 'equal-generation', 'equal-state', 'equal-membership']);
