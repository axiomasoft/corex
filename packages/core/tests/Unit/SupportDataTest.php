<?php

declare(strict_types=1);

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;

it('round trips a scope without runtime handles', function (): void {
    $scope = new DataScope('test', 'account', 'workspace', 'resource', 2, 'incarnation', str_repeat('a', 64), 'agent', 'principal');
    expect(unserialize(serialize($scope)))->toEqual($scope);
});

it('keeps record references opaque and bounded', function (): void {
    $record = new RecordRef(entity: 'contact', id: '01J0A9F9W65A20T0W5KAPYPN8Z');

    expect($record->entity)->toBe('contact')
        ->and($record->id)->toBe('01J0A9F9W65A20T0W5KAPYPN8Z');
});

it('rejects invalid record references', function (string $entity, string $id): void {
    expect(fn (): RecordRef => new RecordRef(entity: $entity, id: $id))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'empty entity' => ['', 'record-1'],
    'empty identifier' => ['contact', ''],
    'oversized identifier' => ['contact', str_repeat('x', 129)],
]);

it('keeps write tokens opaque and binds them to a canonical payload digest', function (): void {
    $token = new WriteToken(
        operationKey: 'request-1',
        payloadHash: str_repeat('b', 64),
        expectedRecordVersion: 'absent',
    );

    expect($token->operationKey)->toBe('request-1')
        ->and($token->payloadHash)->toBe(str_repeat('b', 64))
        ->and($token->expectedRecordVersion)->toBe('absent');
});

it('rejects invalid write-token values', function (string $operationKey, string $payloadHash, string $expectedRecordVersion): void {
    expect(fn (): WriteToken => new WriteToken(
        operationKey: $operationKey,
        payloadHash: $payloadHash,
        expectedRecordVersion: $expectedRecordVersion,
    ))->toThrow(InvalidArgumentException::class);
})->with([
    'empty operation key' => ['', str_repeat('c', 64), '1'],
    'non canonical payload digest' => ['request-1', str_repeat('C', 64), '1'],
    'empty expected record version' => ['request-1', str_repeat('c', 64), ''],
    'oversized expected record version' => ['request-1', str_repeat('c', 64), str_repeat('v', 129)],
]);
