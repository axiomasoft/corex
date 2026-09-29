<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Data\ArrayDataScopeCodec;
use CoreX\Data\DataScope;
use CoreX\Data\Exceptions\InvalidScopePayload;

function codecSampleScope(?string $workspaceId = null, bool $boxed = true): DataScope
{
    $account = new AccountRef(
        id: 'account-1',
        slug: 'account-1',
        status: 'active',
        features: [],
        limits: [],
    );
    $workspace = $workspaceId === null
        ? null
        : new WorkspaceRef(
            id: $workspaceId,
            slug: 'main',
            type: 'team',
            path: '/main',
            parentId: null,
        );

    return DataScope::fromTenantContext(
        context: new TenantContext(account: $account, workspace: $workspace, boxed: $boxed),
        connectionIdentity: 'boxed:account-1',
        actorId: 'user-1',
        restoreEpoch: str_repeat('a', 64),
    );
}

it('round-trips encoded payload with and without workspace', function (): void {
    $codec = new ArrayDataScopeCodec;

    $withoutWorkspace = codecSampleScope();
    expect($codec->decode($codec->encode($withoutWorkspace)))
        ->accountId->toBe('account-1')
        ->and($codec->decode($codec->encode($withoutWorkspace))->workspaceId)->toBeNull()
        ->and($codec->decode($codec->encode($withoutWorkspace))->boxed)->toBeTrue();

    $withWorkspace = codecSampleScope(workspaceId: 'workspace-1', boxed: false);
    $encoded = $codec->encode($withWorkspace);

    expect(array_keys($encoded))->toBe([
        'version',
        'account',
        'workspace',
        'connection',
        'actor',
        'restoreEpoch',
        'boxed',
    ])
        ->and($codec->decode($encoded))
        ->workspaceId->toBe('workspace-1')
        ->and($codec->decode($encoded)->boxed)->toBeFalse();
});

it('rejects unsupported payload version', function (): void {
    $codec = new ArrayDataScopeCodec;
    $payload = $codec->encode(codecSampleScope());
    $payload['version'] = 1;

    $codec->decode($payload);
})->throws(InvalidScopePayload::class, 'Unsupported data scope payload version [1].');

it('rejects missing connection key', function (): void {
    $codec = new ArrayDataScopeCodec;
    $payload = $codec->encode(codecSampleScope());
    unset($payload['connection']);

    $codec->decode($payload);
})->throws(InvalidScopePayload::class, 'Data scope payload key [connection] is missing or malformed.');

it('rejects absent workspace key', function (): void {
    $codec = new ArrayDataScopeCodec;
    $payload = $codec->encode(codecSampleScope());
    unset($payload['workspace']);

    $codec->decode($payload);
})->throws(InvalidScopePayload::class, 'Data scope payload key [workspace] is missing or malformed.');

it('accepts explicit null workspace key', function (): void {
    $codec = new ArrayDataScopeCodec;
    $payload = $codec->encode(codecSampleScope());
    $payload['workspace'] = null;

    expect($codec->decode($payload)->workspaceId)->toBeNull();
});

it('rejects malformed boxed value', function (): void {
    $codec = new ArrayDataScopeCodec;
    $payload = $codec->encode(codecSampleScope());
    $payload['boxed'] = 'true';

    $codec->decode($payload);
})->throws(InvalidScopePayload::class, 'Data scope payload key [boxed] is missing or malformed.');
