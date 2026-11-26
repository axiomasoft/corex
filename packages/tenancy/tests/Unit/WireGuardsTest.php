<?php

declare(strict_types=1);

use CoreX\Tenancy\DomainTrust\Data\NormalizedHost;
use CoreX\Tenancy\Membership\Exceptions\CentralProtocolViolation;
use CoreX\Tenancy\Membership\Internal\Transport\Value;
use CoreX\Tenancy\Membership\Laravel\MembershipSnapshotFactory;

it('normalizes a public DNS hostname', function (): void {
    expect((string) new NormalizedHost('Shop.Example.COM'))->toBe('shop.example.com')
        ->and((string) new NormalizedHost(str_repeat('a', 63).'.'.str_repeat('b', 63)))->toHaveLength(127);
});

it('rejects reserved, non-ASCII, overlong and malformed hosts', function (string $host): void {
    new NormalizedHost($host);
})->throws(InvalidArgumentException::class)->with([
    'localhost' => ['LOCALHOST'],
    'non-ASCII' => ['пример.рф'],
    'over 253 bytes' => [implode('.', array_fill(0, 5, str_repeat('a', 60)))],
    'single label' => ['intranet'],
    'empty label' => ['shop..example.com'],
    'leading hyphen' => ['-shop.example.com'],
    'label over 63' => [str_repeat('a', 64).'.example.com'],
    'underscore' => ['shop_1.example.com'],
]);

it('rejects a wire field that is not a string as a protocol violation', function (): void {
    expect(fn (): string => Value::string(payload: ['kind' => 7], field: 'kind'))->toThrow(CentralProtocolViolation::class)
        ->and(fn (): string => Value::string(payload: [], field: 'kind'))->toThrow(CentralProtocolViolation::class);
});

it('rejects a membership snapshot whose membership id is not a string', function (): void {
    $payload = array_fill_keys(['profile_version', 'request_id', 'identity_id', 'account_id', 'product', 'membership_id', 'state', 'revision', 'revoke_generation', 'read_started_at', 'observed_at'], 'x');
    $payload['membership_id'] = 42;

    MembershipSnapshotFactory::fromPayload($payload);
})->throws(CentralProtocolViolation::class, 'Invalid nullable membership UUID.');
