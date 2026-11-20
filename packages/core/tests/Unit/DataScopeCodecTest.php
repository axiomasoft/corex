<?php

declare(strict_types=1);

use CoreX\Data\Data\DataScope;
use CoreX\Data\Exceptions\InvalidScopePayload;
use CoreX\Data\Internal\ArrayDataScopeCodec;

function codecSampleScope(?string $workspace = null): DataScope
{
    return new DataScope('test', 'account', $workspace, 'resource', 3, 'incarnation', str_repeat('a', 64), 'user', 'person');
}

it('round trips the sole scope format including null and present workspaces', function (?string $workspace): void {
    $scope = codecSampleScope($workspace);
    $codec = new ArrayDataScopeCodec;
    $payload = $codec->encode($scope);
    expect($payload)->toBe([
        'version' => 3, 'deployment_id' => 'test', 'account_id' => 'account', 'workspace_id' => $workspace,
        'binding_id' => 'resource', 'binding_generation' => 3, 'account_incarnation' => 'incarnation',
        'restore_epoch' => str_repeat('a', 64), 'principal_kind' => 'user', 'principal_id' => 'person',
    ])->and($codec->decode($payload))->toEqual($scope);
})->with([null, 'workspace-2']);

it('rejects missing, legacy and malformed scope fields', function (string $field, mixed $value): void {
    $codec = new ArrayDataScopeCodec;
    $payload = $codec->encode(codecSampleScope());

    if ($value === 'omit') {
        unset($payload[$field]);
    } else {
        $payload[$field] = $value;
    }
    expect(fn () => $codec->decode($payload))->toThrow(InvalidScopePayload::class);
})->with([
    ['version', 2], ['version', '3'], ['workspace_id', 'omit'], ['binding_generation', '3'],
    ['binding_generation', 0], ['account_incarnation', ''], ['restore_epoch', str_repeat('A', 64)],
    ['principal_kind', 'system'], ['principal_id', false], ['connection', 'old'], ['account_id', 'omit'],
]);

it('refuses a complete legacy queue scope even if its producer rewrites the version number', function (): void {
    $codec = new ArrayDataScopeCodec;
    $legacy = ['version' => 2, 'account' => 'account', 'workspace' => null, 'connection' => 'tenant',
        'actor' => 'person', 'restoreEpoch' => str_repeat('a', 64), 'boxed' => false];
    expect(fn () => $codec->decode($legacy))->toThrow(InvalidScopePayload::class)
        ->and(fn () => $codec->decode([...$legacy, 'version' => DataScope::VERSION]))->toThrow(InvalidScopePayload::class);
});
