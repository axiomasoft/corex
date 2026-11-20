<?php

declare(strict_types=1);

use CoreX\Scheduler\Internal\WakeupHandlerRegistry;
use CoreX\Scheduling\Contracts\DueWakeupHandler;
use CoreX\Scheduling\Data\WakeupDelivery;
use CoreX\Scheduling\Data\WakeupHandlerDefinition;

final class RegistryTestHandler implements DueWakeupHandler
{
    public function handle(WakeupDelivery $delivery): void {}
}

final class NotAWakeupHandler {}

function compileWakeupRegistry(array $definitions, string $fingerprint = 'registry-a'): WakeupHandlerRegistry
{
    return WakeupHandlerRegistry::compile(
        definitions: $definitions,
        registryFingerprint: $fingerprint,
    );
}

it('compiles typed definitions into a frozen handler catalogue', function (): void {
    $definition = new WakeupHandlerDefinition(
        kind: 'test.reminder',
        version: 'reminder/1',
        handler: RegistryTestHandler::class,
    );
    $registry = compileWakeupRegistry([$definition]);

    expect($registry->definition('test.reminder'))->toBe($definition)
        ->and($registry->handler('test.reminder', static fn (string $handler): DueWakeupHandler => new $handler))->toBeInstanceOf(RegistryTestHandler::class)
        ->and($registry->definition('test.missing'))->toBeNull();
});

it('rejects duplicate, malformed and non-handler definitions', function (): void {
    $definition = new WakeupHandlerDefinition(
        kind: 'test.reminder',
        version: 'reminder/1',
        handler: RegistryTestHandler::class,
    );

    expect(fn () => compileWakeupRegistry([$definition, $definition]))
        ->toThrow(RuntimeException::class, 'Duplicate wakeup handler definition')
        ->and(fn () => new WakeupHandlerDefinition(
            kind: 'Invalid Kind',
            version: 'reminder/1',
            handler: RegistryTestHandler::class,
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new WakeupHandlerDefinition(
            kind: 'test.reminder',
            version: '',
            handler: RegistryTestHandler::class,
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new WakeupHandlerDefinition(
            kind: 'test.reminder',
            version: 'reminder/1',
            handler: NotAWakeupHandler::class,
        ))->toThrow(InvalidArgumentException::class, 'must implement DueWakeupHandler');
});

it('stores registry provenance on each stamped envelope', function (): void {
    $definition = new WakeupHandlerDefinition(
        kind: 'test.reminder',
        version: 'reminder/1',
        handler: RegistryTestHandler::class,
    );
    $registryA = compileWakeupRegistry([$definition], 'registry-a');
    $registryB = compileWakeupRegistry([$definition], 'registry-b');
    $envelopeA = $registryA->envelope($definition, ['message' => 'wake']);
    $envelopeB = $registryB->envelope($definition, ['message' => 'wake']);

    expect($envelopeA['_corex']['schema'])->toBe('wakeup-payload/2')
        ->and($envelopeA['_corex']['extension'])->toBe([
            'point' => 'wakeup.handler',
            'id' => 'test.reminder',
            'version' => 'reminder/1',
            'registryFingerprint' => 'registry-a',
        ])
        ->and($envelopeB['_corex']['extension']['registryFingerprint'])->toBe('registry-b');
});

it('classifies exact, drifted, missing and malformed payloads', function (): void {
    $strict = new WakeupHandlerDefinition(
        kind: 'test.reminder',
        version: 'reminder/1',
        handler: RegistryTestHandler::class,
    );
    $registry = compileWakeupRegistry([$strict]);
    $exact = $registry->envelope($strict, ['message' => 'wake']);
    $fingerprintDrift = $exact;
    $fingerprintDrift['_corex']['extension']['registryFingerprint'] = 'registry-b';
    $versionDrift = $exact;
    $versionDrift['_corex']['extension']['version'] = 'reminder/2';

    expect($registry->inspectPayload('test.reminder', $exact))->toBe([
        'status' => 'ready',
        'data' => ['message' => 'wake'],
    ])->and($registry->inspectPayload('test.reminder', $fingerprintDrift))->toBe([
        'status' => 'ready',
        'data' => ['message' => 'wake'],
    ])->and($registry->inspectPayload('test.reminder', $versionDrift)['status'])
        ->toBe('handler-version-mismatch')
        ->and($registry->inspectPayload('test.reminder', ['message' => 'wake'])['status'])
        ->toBe('handler-stamp-missing');
});
