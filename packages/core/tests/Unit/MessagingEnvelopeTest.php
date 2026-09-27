<?php

declare(strict_types=1);

use CoreX\Enums\MessageClass;
use CoreX\Enums\PiiClass;
use CoreX\Exceptions\InvalidMessage;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;

function messagingSampleEnvelope(array $overrides = []): MessageEnvelope
{
    return new MessageEnvelope(
        messageId: $overrides['messageId'] ?? 'msg-1',
        source: $overrides['source'] ?? 'corex/core',
        name: $overrides['name'] ?? 'membership.expired',
        contractVersion: $overrides['contractVersion'] ?? 1,
        occurredAt: $overrides['occurredAt'] ?? new DateTimeImmutable('2026-01-01T00:00:00Z'),
        scope: $overrides['scope'] ?? ['accountId' => 'account-1'],
        payload: $overrides['payload'] ?? MessagePayload::empty(),
        piiClass: $overrides['piiClass'] ?? PiiClass::None,
        subject: $overrides['subject'] ?? 'subject-1',
        correlationId: $overrides['correlationId'] ?? 'corr-1',
        causationId: $overrides['causationId'] ?? 'cause-1',
        metadata: $overrides['metadata'] ?? ['trace' => 't1'],
        envelopeVersion: $overrides['envelopeVersion'] ?? MessageEnvelope::ENVELOPE_VERSION,
    );
}

it('builds a full envelope and exposes contract identity', function (): void {
    $envelope = messagingSampleEnvelope();

    expect($envelope->contract())->toBe('membership.expired.v1')
        ->and($envelope->messageId)->toBe('msg-1')
        ->and($envelope->metadata)->toBe(['trace' => 't1']);
});

it('rejects empty message identity', function (): void {
    messagingSampleEnvelope(['messageId' => '']);
})->throws(InvalidMessage::class, 'Invalid message message identity.');

it('rejects an overlong message source', function (): void {
    messagingSampleEnvelope(['source' => str_repeat('a', 129)]);
})->throws(InvalidMessage::class, 'Invalid message message source.');

it('rejects a control character in message subject', function (): void {
    messagingSampleEnvelope(['subject' => "sub\x01ject"]);
})->throws(InvalidMessage::class, 'Invalid message message subject.');

it('rejects an unknown envelope version', function (): void {
    messagingSampleEnvelope(['envelopeVersion' => 2]);
})->throws(InvalidMessage::class, 'Invalid message envelope version [2].');

it('rejects an empty durable scope', function (): void {
    messagingSampleEnvelope(['scope' => []]);
})->throws(InvalidMessage::class, 'RUN-002');

it('rejects payload nesting beyond eight levels', function (): void {
    $nested = ['l' => 1];
    $current = &$nested;
    for ($i = 0; $i < 9; $i++) {
        $current['n'] = ['l' => 1];
        $current = &$current['n'];
    }

    new MessagePayload($nested);
})->throws(InvalidMessage::class, 'nesting exceeds eight levels');

it('rejects a top-level list payload', function (): void {
    new MessagePayload([1, 2, 3]);
})->throws(InvalidMessage::class, 'must be a bounded object map, not a list');

it('rejects too many payload keys', function (): void {
    $values = [];
    for ($i = 0; $i < 257; $i++) {
        $values["k{$i}"] = 1;
    }

    new MessagePayload($values);
})->throws(InvalidMessage::class, 'contains too many values');

it('rejects unsupported payload values', function (): void {
    new MessagePayload(['obj' => new stdClass]);
})->throws(InvalidMessage::class, 'contains an unsupported value');

it('rejects payload exceeding 64KiB', function (): void {
    new MessagePayload(['blob' => str_repeat('x', 70 * 1024)]);
})->throws(InvalidMessage::class, 'exceeds 64KiB');

it('canonicalizes payload keys recursively', function (): void {
    $encoded = (new MessagePayload(['b' => 1, 'a' => ['d' => 1, 'c' => 2]]))->encode();

    expect($encoded)->toBe('{"a":{"c":2,"d":1},"b":1}');
});

it('rejects a PHP class name as contract name', function (): void {
    new MessageContract(
        name: 'CoreX\Events\Foo',
        version: 1,
        class: MessageClass::IntegrationEvent,
        owner: 'acme/widgets',
        maxPiiClass: PiiClass::None,
        channel: 'default',
    );
})->throws(InvalidMessage::class, 'must be a dotted lowercase name');

it('rejects domain events as published contracts', function (): void {
    new MessageContract(
        name: 'membership.expired',
        version: 1,
        class: MessageClass::DomainEvent,
        owner: 'acme/widgets',
        maxPiiClass: PiiClass::None,
        channel: 'default',
    );
})->throws(InvalidMessage::class, 'MSG-002');

it('orders pii class sensitivity for permits', function (): void {
    expect(PiiClass::Pseudonymous->permits(PiiClass::Personal))->toBeFalse()
        ->and(PiiClass::Personal->permits(PiiClass::None))->toBeTrue();
});
