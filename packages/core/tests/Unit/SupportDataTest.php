<?php

declare(strict_types=1);

use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Support\Data\CallableDataScopeAuthorizer;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;
use CoreX\Support\Data\WriteToken;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;

function dataScope(): DataScope
{
    return new DataScope(
        context: new TenantContext(
            account: new AccountRef(
                id: 'account-1',
                slug: 'account-1',
                status: 'active',
                features: [],
                limits: [],
            ),
            boxed: true,
        ),
        connectionIdentity: 'boxed:account-1',
        actorId: 'user-1',
        restoreEpoch: str_repeat('a', 64),
    );
}

function dataScopeAuthorizer(): DataScopeAuthorizer
{
    return new CallableDataScopeAuthorizer(
        static fn (DataScope $_scope, string $entity, string $record, array $fields, string $action): bool => $entity === 'contact'
            && $record === 'record-1'
            && $fields === ['name']
            && $action === 'write',
    );
}

it('carries only trusted data-boundary context', function (): void {
    $scope = dataScope();
    $authorizer = dataScopeAuthorizer();

    expect($scope->connectionIdentity)->toBe('boxed:account-1')
        ->and($scope->actorId)->toBe('user-1')
        ->and($authorizer->authorize(scope: $scope, entity: 'contact', subjectId: 'record-1', fields: ['name'], action: 'write')->allowed)->toBeTrue()
        ->and($authorizer->authorize(scope: $scope, entity: 'contact', subjectId: 'record-1', fields: ['name'], action: 'read')->allowed)->toBeFalse();
});

it('serializes and unserializes without exceptions', function (): void {
    $scope = dataScope();

    $restored = unserialize(serialize($scope));

    expect($restored)->toBeInstanceOf(DataScope::class)
        ->and($restored->connectionIdentity)->toBe($scope->connectionIdentity)
        ->and($restored->actorId)->toBe($scope->actorId)
        ->and($restored->restoreEpoch)->toBe($scope->restoreEpoch);
});

it('rejects invalid trusted data-boundary scalar values', function (string $connectionIdentity, string $actorId, string $restoreEpoch): void {
    expect(fn (): DataScope => new DataScope(
        context: dataScope()->context,
        connectionIdentity: $connectionIdentity,
        actorId: $actorId,
        restoreEpoch: $restoreEpoch,
    ))->toThrow(InvalidArgumentException::class);
})->with([
    'empty connection identity' => ['', 'user-1', str_repeat('a', 64)],
    'empty actor identity' => ['boxed:account-1', '', str_repeat('a', 64)],
    'non canonical restore epoch' => ['boxed:account-1', 'user-1', str_repeat('A', 64)],
]);

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
