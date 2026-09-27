<?php

declare(strict_types=1);

use CoreX\Api\Enums\IdempotencyRequirement;
use CoreX\Api\Enums\OperationMode;
use CoreX\Api\Operation;
use CoreX\Api\Tests\Support\OperationHandlerDouble;
use CoreX\Modules\Extend\Api;

$schema = ['type' => 'object'];

it('projects compiler metadata onto the runtime descriptor', function () use ($schema): void {
    $api = Api::operation('notes.create', 'POST', '/notes', OperationHandlerDouble::class, 'notes.write', $schema, $schema)
        ->asCommand()
        ->requiresScopes('notes.write')
        ->idempotency('required')
        ->fields(read: ['id'], write: ['name'])
        ->capability('notes.write.v1')
        ->effect('tenant_transactional')
        ->deprecated('notes.create', '2026-09-01', null, 'notes.create.v2', 'https://example.test/migrate');
    $api->declaringModule = 'example/notes';

    $operation = Operation::fromDeclaration($api, 'fingerprint-a');
    $again = Operation::fromDeclaration($api, 'fingerprint-a');
    $otherFingerprint = Operation::fromDeclaration($api, 'fingerprint-b');

    expect($operation->mode)->toBe(OperationMode::Command)
        ->and($operation->idempotency)->toBe(IdempotencyRequirement::Required)
        ->and($operation->requiredScopes)->toBe(['notes.write'])
        ->and($operation->readFields)->toBe(['id'])
        ->and($operation->writeFields)->toBe(['name'])
        ->and($operation->capabilities)->toBe(['notes.write.v1'])
        ->and($operation->effectMode)->toBe('tenant_transactional')
        ->and($operation->executionSupported)->toBeTrue()
        ->and($operation->contractHash)->toBe($again->contractHash)
        ->and($operation->contractHash)->not->toBe($otherFingerprint->contractHash)
        ->and($operation->deprecation['id'])->toBe('notes.create');
});
