<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/Messaging.php';

use CoreX\Data\Data\RecordRef;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Exceptions\InvalidMessage;

it('builds a full envelope and exposes contract identity', function (): void {
    $envelope = messagingSampleEnvelope();

    expect($envelope->contract())->toBe('membership.expired.v1')
        ->and($envelope->messageId)->toBe('msg-1')
        ->and($envelope->metadata)->toBe([]);
});

it('rejects empty message identity', function (): void {
    messagingSampleEnvelope(['messageId' => '']);
})->throws(InvalidMessage::class, 'Invalid message message identity.');

it('rejects an overlong message source', function (): void {
    messagingSampleEnvelope(['source' => str_repeat('a', 129)]);
})->throws(InvalidMessage::class, 'Invalid message message source.');

it('rejects a control character in message subject', function (): void {
    new RecordRef("sub\x01ject", 'id');
})->throws(InvalidArgumentException::class);

it('rejects an unknown envelope version', function (): void {
    messagingSampleEnvelope(['envelopeVersion' => 1]);
})->throws(InvalidMessage::class, 'Invalid message envelope version [1].');

it('rejects an empty durable scope', function (): void {
    messagingSampleEnvelope(['scope' => []]);
})->throws(TypeError::class);

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
    new MessagePayload(['obj' => new DateTimeImmutable]);
})->throws(InvalidMessage::class, 'contains an unsupported value');

it('rejects mutable objects nested inside PHP payload maps', function (): void {
    new MessagePayload(['nested' => [['x' => new stdClass]]]);
})->throws(InvalidMessage::class, 'contains an unsupported value');

it('rejects payload exceeding 64KiB', function (): void {
    new MessagePayload(['blob' => str_repeat('x', 70 * 1024)]);
})->throws(InvalidMessage::class, 'exceeds 64KiB');

it('canonicalizes payload keys recursively', function (): void {
    $encoded = new MessagePayload(['b' => 1, 'a' => ['d' => 1, 'c' => 2]])->encode();

    expect($encoded)->toBe('{"a":{"c":2,"d":1},"b":1}');
});

it('rejects a PHP class name as contract name', function (): void {
    new MessageContract(
        name: 'CoreX\Messaging\Events\Foo',
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

it('preserves typed inputs and exact decimal strings in the immutable fact digest', function (): void {
    $digests = [];
    foreach ([[], ['k' => null], ['k' => ''], ['k' => 1], ['k' => '1'], ['k' => '1.0000000000000000001']] as $values) {
        $digests[] = messagingSampleEnvelope(['payload' => new MessagePayload($values)])->digest();
    }
    expect(array_unique($digests))->toHaveCount(6)
        ->and(fn () => new MessagePayload(['k' => 1.0]))->toThrow(InvalidMessage::class);
});

it('round trips distinct empty objects and lists in durable JSON', function (): void {
    $map = MessagePayload::fromJson('{"x":{}}');
    $list = MessagePayload::fromJson('{"x":[]}');
    expect($map->encode())->toBe('{"x":{}}')->and($list->encode())->toBe('{"x":[]}')
        ->and(messagingSampleEnvelope(['payload' => $map])->digest())->not->toBe(messagingSampleEnvelope(['payload' => $list])->digest());
});
