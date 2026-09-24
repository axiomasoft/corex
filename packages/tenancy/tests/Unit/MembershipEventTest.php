<?php

declare(strict_types=1);

use CoreX\Tenancy\Membership\Transport\CentralProtocolViolation;
use CoreX\Tenancy\Membership\Transport\CentralUnauthorized;
use CoreX\Tenancy\Membership\Transport\JwsVerifier;
use CoreX\Tenancy\Membership\Transport\MembershipQuery;
use CoreX\Tenancy\Membership\Transport\StrictJson;
use CoreX\Tenancy\Membership\Transport\Value;
use CoreX\Tenancy\Tests\Support\Membership\TransportFixture;

it('rejects duplicate scalar arrays conflicting query and noncanonical encoding', function (string $wire): void {
    expect(fn () => MembershipQuery::fromWireQuery($wire))->toThrow(CentralProtocolViolation::class);
})->with([
    'identity_id=x&identity_id=y&account_id=x&product=x&request_id=x',
    'identity_id[]=x&account_id=x&product=x&request_id=x',
    'identity_id%5B%5D=x&account_id=x&product=x&request_id=x',
    'identity_id=x&account_id=x&product=&request_id=x',
    'identity_id=x&account_id=x&product=a+b&request_id=x',
    'identity_id=x&account_id=x&product=%xx&request_id=x',
    'identity_id=x&account_id=x&product=x&request_id=x&unknown=y',
    'identity_id=x&account_id=x&product=x&request_id=x=extra',
]);

it('roundtrips the proposed ordered RFC3986 scalar query fixture', function (): void {
    $query = new MembershipQuery(identityId: TransportFixture::IDENTITY, accountId: TransportFixture::ACCOUNT, product: 'reference:app', requestId: TransportFixture::REQUEST);
    expect(get_object_vars(MembershipQuery::fromWireQuery($query->wireQuery())))->toBe(get_object_vars($query));
    expect(fn () => MembershipQuery::fromWireQuery(str_replace('%3A', '%3a', $query->wireQuery())))->toThrow(CentralProtocolViolation::class);
    expect(fn () => MembershipQuery::fromWireQuery($query->wireQuery().'&product=other'))->toThrow(CentralProtocolViolation::class);
});

it('rejects duplicate JSON escaped keys nested keys malformed objects and excessive size', function (string $wire): void {
    expect(fn () => StrictJson::object($wire))->toThrow(CentralProtocolViolation::class);
})->with(['{"a":1,"a":2}', '{"a":1,"\u0061":2}', '{"cnf":{"x":1,"x":2}}', '{"a":[{"x":1,"x":2}]}', '{"a":1,}', '[]', '{"a":1} {}', '{"a":"'.str_repeat('x', 65536).'"}']);

it('decodes legitimate JSON escaping arrays booleans and distinct object keys', function (): void {
    $wire = json_encode(['a' => 'quote" and slash\\ and Unicode b', 'b' => [true, false, null, (object) ['x' => 2]], '0' => 3, '00' => 4], JSON_THROW_ON_ERROR);
    expect(StrictJson::object($wire))->toEqual((array) json_decode($wire));
});

it('keeps exact unsigned64 decimal values without lossy casts', function (string $value): void {
    expect(Value::counter($value))->toBe($value);
})->with(['0', '9', '10', '9007199254740991', '9007199254740992', '9007199254740993', '9223372036854775807', '9223372036854775808', '18446744073709551615']);

it('rejects noncanonical fractional exponential and overflowing counters', function (string $value): void {
    expect(fn () => Value::counter($value))->toThrow(CentralProtocolViolation::class);
})->with(['-1', '+1', '01', '1.0', '1e2', 'NaN', 'Infinity', '18446744073709551616', ' 1', '']);

it('rejects malformed and noncanonical UUIDs', function (string $id): void {
    expect(fn () => Value::uuid($id))->toThrow(CentralProtocolViolation::class);
})->with(['123', '11111111-1111-4111-7111-111111111111', 'AAAAAAAA-AAAA-4AAA-8AAA-AAAAAAAAAAAA', '11111111-1111-0111-8111-111111111111']);

it('authenticates trigger only and permits fresh re-enveloping of one logical event', function (): void {
    $fixture = new TransportFixture;

    try {
        $parser = $fixture->parser();
        $claims = $fixture->eventClaims();
        $first = $parser->parse(compact: $fixture->sign($claims, event: true), now: $fixture->now);
        $retry = [...$claims, 'jti' => TransportFixture::MEMBERSHIP, 'iat' => $fixture->now + 301, 'exp' => $fixture->now + 601];
        $next = $parser->parse(compact: $fixture->sign($retry, event: true), now: $fixture->now + 301);
        expect($next->eventId)->toBe($first->eventId)->and($next->deliveryId)->not->toBe($first->deliveryId)
            ->and($next->logicalDigest())->toBe($first->logicalDigest())
            ->and(get_object_vars($first))->not->toHaveKeys(['active', 'state', 'lease', 'generation', 'email', 'phone', 'name']);
        $conflict = [...$retry, 'reason' => 'removed'];
        expect(fn () => $parser->parse(compact: $fixture->sign($conflict, event: true), now: $fixture->now + 301))->toThrow(CentralProtocolViolation::class);
        expect($parser->parse(compact: $fixture->sign($retry, event: true), now: $fixture->now + 301)->logicalDigest())->toBe($first->logicalDigest());
    } finally {
        $fixture->cleanup();
    }
});

it('denies unsigned expired wrong context and unnegotiated trigger payloads', function (string $case): void {
    $fixture = new TransportFixture;

    try {
        $p = $fixture->eventClaims();
        $header = [];
        $expected = CentralProtocolViolation::class;
        switch ($case) {
            case 'account': $p['account_id'] = TransportFixture::OTHER;
                $expected = CentralUnauthorized::class;

                break;
            case 'product': $p['product'] = 'other';
                $expected = CentralUnauthorized::class;

                break;
            case 'issuer': $p['iss'] = 'https://other.example.test';
                $expected = CentralUnauthorized::class;

                break;
            case 'audience': $p['aud'] = 'https://other.example.test';
                $expected = CentralUnauthorized::class;

                break;
            case 'expired': $p['iat'] = $fixture->now - 332;
                $p['exp'] = $fixture->now - 32;
                $expected = CentralUnauthorized::class;

                break;
            case 'long-ttl': $p['exp'] = $fixture->now + 301;
                $expected = CentralUnauthorized::class;

                break;
            case 'future': $p['iat'] = $fixture->now + 31;
                $p['exp'] = $fixture->now + 300;
                $expected = CentralUnauthorized::class;

                break;
            case 'iat-type': $p['iat'] = (string) $fixture->now;
                $expected = CentralUnauthorized::class;

                break;
            case 'kind': $p['kind'] = 'grant-and-authorize';

                break;
            case 'reason': $p['reason'] = 'free text';

                break;
            case 'uuid': $p['identity_id'] = '123';

                break;
            case 'actor': $p['acting_identity_id'] = 'person@example.test';

                break;
            case 'no-actor': unset($p['acting_identity_id']);

                break;
            case 'pii': $p['email'] = 'person@example.test';

                break;
            case 'profile': $p['profile_version'] = 'other/1';

                break;
            case 'none': $header['alg'] = 'none';
                $expected = CentralUnauthorized::class;

                break;
            case 'unknown-key': $header['kid'] = 'unknown';
                $expected = CentralUnauthorized::class;

                break;
            case 'key-purpose': $header['kid'] = 'access';
                $expected = CentralUnauthorized::class;

                break;
            case 'token-type': $header['typ'] = 'at+jwt';
                $expected = CentralUnauthorized::class;

                break;
            case 'jku': $header['jku'] = 'https://untrusted.example.test/keys';

                break;
            case 'x5u': $header['x5u'] = 'https://untrusted.example.test/key';

                break;
            case 'critical': $header['crit'] = ['unrecognized'];

                break;
        }
        expect(fn () => $fixture->parser()->parse(compact: $fixture->sign($p, header: $header, event: true), now: $fixture->now))->toThrow($expected);
    } finally {
        $fixture->cleanup();
    }
})->with(['account', 'product', 'issuer', 'audience', 'expired', 'long-ttl', 'future', 'iat-type', 'kind', 'reason', 'uuid', 'actor', 'no-actor', 'pii', 'profile', 'none', 'unknown-key', 'key-purpose', 'token-type', 'jku', 'x5u', 'critical']);

it('rejects tampered and malformed signatures with zero key retrieval capability', function (): void {
    $fixture = new TransportFixture;

    try {
        $token = $fixture->sign($fixture->eventClaims(), event: true);
        $parts = explode('.', $token);
        $parts[1] = TransportFixture::encode(json_encode([...$fixture->eventClaims(), 'reason' => 'removed'], JSON_THROW_ON_ERROR));
        expect(fn () => $fixture->parser()->parse(implode('.', $parts), $fixture->now))->toThrow(CentralUnauthorized::class);
        expect(fn () => $fixture->parser()->parse('unsigned', $fixture->now))->toThrow(CentralUnauthorized::class);
        expect(fn () => $fixture->parser()->parse($token.'=', $fixture->now))->toThrow(CentralUnauthorized::class);
        $emptyKeys = new JwsVerifier([]);
        expect(fn () => $emptyKeys->verify(compact: $token, type: 'corex-membership-trigger+jwt', purpose: 'trigger'))->toThrow(CentralUnauthorized::class);
    } finally {
        $fixture->cleanup();
    }
});
