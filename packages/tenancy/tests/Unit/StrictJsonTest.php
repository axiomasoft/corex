<?php

declare(strict_types=1);

use CoreX\Tenancy\Membership\Exceptions\CentralProtocolViolation;
use CoreX\Tenancy\Membership\Internal\Transport\StrictJson;

it('preserves wire strings large counters and nested members', function (): void {
    $decoded = StrictJson::object('{"revision":18446744073709551615,"name":"a\"b","items":[true,null,{"name":"value"}]}');

    expect($decoded['revision'])->toBe('18446744073709551615')
        ->and($decoded['name'])->toBe('a"b')
        ->and($decoded['items'])->toEqual([true, null, (object) ['name' => 'value']]);
});

it('rejects ambiguous or malformed wire objects', function (string $wire): void {
    expect(fn () => StrictJson::object($wire))->toThrow(CentralProtocolViolation::class);
})->with([
    'duplicate root member' => ['{"revision":1,"revision":2}'],
    'duplicate nested member' => ['{"items":[{"state":"active","state":"revoked"}]}'],
    'array root' => ['[]'],
    'invalid scalar' => ['{"revision":no}'],
    'trailing value' => ['{} true'],
]);

it('keeps numeric object keys in the decoder but refuses unnamed protocol fields', function (): void {
    expect(StrictJson::object('{"0":3,"00":4}'))->toBe([0 => 3, '00' => 4])
        ->and(fn () => StrictJson::namedObject('{"0":3}'))->toThrow(CentralProtocolViolation::class)
        ->and(StrictJson::namedObject('{"revision":"1"}'))->toBe(['revision' => '1']);
});
