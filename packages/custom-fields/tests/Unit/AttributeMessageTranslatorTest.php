<?php

declare(strict_types=1);

use CoreX\CustomFields\Internal\Integration\AttributeMessageContractRegistry;
use CoreX\CustomFields\Internal\Integration\AttributeMessageContracts;
use CoreX\CustomFields\Internal\Integration\AttributeMessageTranslator;
use CoreX\CustomFields\Internal\Integration\AttributeValuesChanged;
use CoreX\Data\Data\RecordRef;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Testing\MessagingFixtures;

function attributeValuesChanged(): AttributeValuesChanged
{
    return new AttributeValuesChanged(
        messageId: '01932f5a-0000-7000-8000-000000000701',
        scope: jsonbScope(),
        recordEntity: 'contact',
        recordId: 'record-1',
        recordVersion: str_repeat('b', 64),
        schemaHash: str_repeat('a', 64),
        changedAttributeIds: [
            '018d5f56-6f83-7a36-b0ac-6eabce87c10c',
            '018d5f56-6f83-7a36-b0ac-6eabce87c10b',
        ],
        changedKeys: ['status', 'email'],
        occurredAt: new DateTimeImmutable('2026-09-24T12:00:00+00:00'),
        correlationId: 'operation-1',
        causationId: 'message-0',
    );
}

it('translates a mutation into the declared metadata-only contract', function (): void {
    $message = new AttributeMessageTranslator()
        ->translate(attributeValuesChanged());

    expect($message->contract())->toBe('attributes.values.changed.v1')
        ->and($message->source)->toBe('axiomasoft/corex-custom-fields')
        ->and($message->envelopeVersion)->toBe(2)
        ->and($message->scope)->toEqual(jsonbScope())
        ->and($message->subject)->toEqual(new RecordRef('contact', 'record-1'))
        ->and($message->piiClass)->toBe(PiiClass::Pseudonymous)
        ->and($message->correlationId)->toBe('operation-1')
        ->and($message->causationId)->toBe('message-0')
        ->and($message->payload->toArray())->toBe([
            'changedAttributeIds' => [
                '018d5f56-6f83-7a36-b0ac-6eabce87c10b',
                '018d5f56-6f83-7a36-b0ac-6eabce87c10c',
            ],
            'changedKeys' => ['email', 'status'],
            'record' => ['entity' => 'contact', 'id' => 'record-1'],
            'recordVersion' => str_repeat('b', 64),
            'schemaHash' => str_repeat('a', 64),
        ]);

    expect($message->payload->encode())
        ->not->toContain('owner@example.test')
        ->not->toContain('oldValue')
        ->not->toContain('newValue');
});

it('declares one additive v1 integration contract', function (): void {
    $existing = MessagingFixtures::contracts(contracts: []);
    $resolved = new AttributeMessageContractRegistry($existing);

    expect($resolved->get(AttributeMessageContracts::VALUES_CHANGED, 1)->id())
        ->toBe('attributes.values.changed.v1');
});

it('rejects a registry collision', function (): void {
    new AttributeMessageContractRegistry(MessagingFixtures::contracts(
        contracts: AttributeMessageContracts::all(),
    ));
})->throws(InvalidArgumentException::class, 'Duplicate message contract');

it('has no search dependency in the integration boundary', function (): void {
    $files = glob(__DIR__.'/../../src/Internal/Integration/*.php');

    expect($files)->not->toBeFalse();
    foreach ($files as $file) {
        expect(file_get_contents($file))->not->toContain('CoreX\\Search');
    }
});
