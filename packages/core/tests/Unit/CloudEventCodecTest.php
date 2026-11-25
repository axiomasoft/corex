<?php

declare(strict_types=1);

use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Exceptions\InvalidMessage;
use CoreX\Messaging\Exceptions\UnknownMessageContract;
use CoreX\Messaging\Internal\ArrayMessageContractRegistry;
use CoreX\Messaging\Internal\JsonCloudEventCodec;

function cloudEventCodec(?string $schema = 'https://schemas.example.test/customfields.attribute.changed/v1.json'): JsonCloudEventCodec
{
    return new JsonCloudEventCodec(new ArrayMessageContractRegistry([
        new MessageContract('customfields.attribute.changed', 1, MessageClass::IntegrationEvent, 'axiomasoft/corex-custom-fields', PiiClass::Personal, 'events', $schema),
    ]), resolve(DataScopeCodec::class));
}

function cloudEventMessage(bool $optional = true): MessageEnvelope
{
    return new MessageEnvelope(
        messageId: '01J9Z6Q4A7', source: 'axiomasoft/corex-custom-fields', name: 'customfields.attribute.changed', contractVersion: 1,
        occurredAt: new DateTimeImmutable('2026-10-03T08:00:00.123456Z'), scope: new DataScope('d1', 'a-1', 'w-7', 'b1', 1, 'i1', str_repeat('f', 64), 'user', 'p1'),
        payload: new MessagePayload(['attribute' => 'color']), piiClass: PiiClass::None, subject: $optional ? new RecordRef('attribute', 'attr-42') : new EntityHandle('attribute'),
        correlationId: 'c-1',
        metadata: $optional ? ['traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'] : [],
    );
}

it('encodes the byte exact CloudEvents example and round trips', function (): void {
    $codec = cloudEventCodec();
    $expected = '{"specversion":"1.0","id":"01J9Z6Q4A7","source":"axiomasoft/corex-custom-fields","type":"customfields.attribute.changed.v1","subject":"{\"entity\":\"attribute\",\"id\":\"attr-42\"}","time":"2026-10-03T08:00:00.123456Z","datacontenttype":"application/json","dataschema":"https://schemas.example.test/customfields.attribute.changed/v1.json","corexscope":"{\"account_id\":\"a-1\",\"account_incarnation\":\"i1\",\"binding_generation\":1,\"binding_id\":\"b1\",\"deployment_id\":\"d1\",\"principal_id\":\"p1\",\"principal_kind\":\"user\",\"restore_epoch\":\"ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff\",\"version\":3,\"workspace_id\":\"w-7\"}","corexpii":"none","corexenvelope":2,"corexcorrelation":"c-1","traceparent":"00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01","data":{"attribute":"color"}}';
    expect($codec->encode(cloudEventMessage()))->toBe($expected)
        ->and($codec->decode($expected))->toEqual(cloudEventMessage());
    $plain = cloudEventMessage(false);
    $plainCodec = cloudEventCodec(null);
    expect($plainCodec->decode($plainCodec->encode($plain)))->toEqual($plain);
    $empty = $plain->withPayload(MessagePayload::empty());
    expect(json_decode($plainCodec->encode($empty))->data)->toBeInstanceOf(stdClass::class)
        ->and($plainCodec->decode($plainCodec->encode($empty)))->toEqual($empty);
});

it('external CloudEvents contains only public attributes and projected data', function (): void {
    $message = cloudEventMessage()->withPayload(new MessagePayload(['id' => 'attr-42', 'secret' => 'hidden']));
    $wire = cloudEventCodec()->encodeExternal($message, ['id' => 'attr-42']);
    expect($wire)->toBe('{"specversion":"1.0","id":"01J9Z6Q4A7","source":"axiomasoft/corex-custom-fields","type":"customfields.attribute.changed.v1","time":"2026-10-03T08:00:00.123456Z","datacontenttype":"application/json","data":{"id":"attr-42"}}')
        ->and($wire)->not->toContain('hidden', 'dataschema', 'corexscope', 'traceparent', 'subject');
    expect(json_decode(cloudEventCodec()->encode($message), true)['dataschema'])->toBe('https://schemas.example.test/customfields.attribute.changed/v1.json');
});

it('rejects malformed CloudEvents', function (array $change): void {
    $codec = cloudEventCodec();
    $event = json_decode($codec->encode(cloudEventMessage()), true, flags: JSON_THROW_ON_ERROR);
    $codec->decode(json_encode(array_replace($event, $change), JSON_THROW_ON_ERROR));
})->with([
    'old spec' => [['specversion' => '0.3']], 'unknown' => [['foo' => 'x']], 'base64' => [['data_base64' => 'eA==']],
    'unversioned' => [['type' => 'customfields.attribute.changed']], 'zoneless' => [['time' => '2026-10-03T08:00:00']],
    'scope list' => [['corexscope' => '[]']], 'bad date' => [['time' => '2026-02-30T08:00:00Z']],
    'invalid pii' => [['corexpii' => 'secret']], 'string envelope' => [['corexenvelope' => '2']], 'legacy' => [['corexenvelope' => 1]],
    'foreign schema' => [['dataschema' => 'https://schemas.example.test/other/v1.json']],
    'data list' => [['data' => [1, 2]]], 'null optional' => [['subject' => null]], 'invalid trace' => [['traceparent' => []]],
])->throws(InvalidMessage::class);

it('rejects broken JSON and non object events', function (string $wire): void {
    cloudEventCodec()->decode($wire);
})->with(['{', '[]', 'null', '{"specversion":"1.0"}'])->throws(InvalidMessage::class);

it('rejects unknown metadata and tracestate without traceparent', function (array $metadata): void {
    $m = cloudEventMessage();
    $bad = new MessageEnvelope($m->messageId, $m->source, $m->name, 1, $m->occurredAt, $m->scope, $m->payload, $m->piiClass, $m->subject, $m->correlationId, metadata: $metadata);
    cloudEventCodec()->encode($bad);
})->with([[['x-tenant' => 'a']], [['tracestate' => 'vendor=value']], [['traceparent' => null]]])->throws(InvalidMessage::class);

it('rejects undeclared contracts', function (): void {
    new JsonCloudEventCodec(new ArrayMessageContractRegistry([]), resolve(DataScopeCodec::class))->encode(cloudEventMessage());
})->throws(UnknownMessageContract::class);

it('accepts URI references in source', function (string $source): void {
    $m = cloudEventMessage();
    expect(new MessageEnvelope('id', $source, $m->name, 1, $m->occurredAt, $m->scope, $m->payload, $m->piiClass, $m->subject, $m->correlationId))->source->toBe($source);
})->with(['corex/relay', 'axiomasoft/corex-custom-fields', 'fixture-address/1', './1:foo', '/a/b', '../a', '?q=a%20b', '#fragment', 'urn:corex:source', 'https://example.test/a%20b?q=x#f', 'https://[::1]/', 'https://[2001:db8::1]:443/a', 'https://[::ffff:192.0.2.1]/', 'https://[v1.a:b]/']);

it('rejects malformed source URI references', function (string $source): void {
    $m = cloudEventMessage();
    new MessageEnvelope('id', $source, $m->name, 1, $m->occurredAt, $m->scope, $m->payload, $m->piiClass, $m->subject, $m->correlationId);
})->with(['', str_repeat('x', 129), 'has space', "a\n", 'источник', '%', '%zz', 'a%2', '1:foo', 'http://[not-ip]/', 'https://[::1', 'https://[1::2::3]/', 'https://host:port/', 'https://host/a[b]', 'https://host/#a#b'])->throws(InvalidMessage::class);

it('accepts absolute schema URIs', function (string $schema): void {
    expect(cloudEventCodec($schema)->encode(cloudEventMessage()))->toContain('"dataschema":"'.$schema.'"');
})->with(['urn:schema:v1', 'https://schemas.example.test/v1.json']);

it('rejects invalid schema URIs', function (string $schema): void {
    cloudEventCodec($schema);
})->with(['relative/path', 'https://host/%zz', 'https://[bad]/', 'https://host/schema#fragment'])->throws(InvalidMessage::class);

it('bounds external JSON trees and rejects non JSON values', function (): void {
    $data = ['value' => 'leaf'];
    for ($depth = 0; $depth < 8; $depth++) {
        $data = ['nested' => $data];
    }
    cloudEventCodec()->encodeExternal(cloudEventMessage(), $data);
})->throws(InvalidMessage::class);

it('refuses a fact above the contract PII limit on encode and on decode', function (): void {
    $strict = new JsonCloudEventCodec(new ArrayMessageContractRegistry([
        new MessageContract('customfields.attribute.changed', 1, MessageClass::IntegrationEvent, 'axiomasoft/corex-custom-fields', PiiClass::None, 'events', 'https://schemas.example.test/customfields.attribute.changed/v1.json'),
    ]), resolve(DataScopeCodec::class));
    $personal = new MessageEnvelope(...[...get_object_vars(cloudEventMessage()), 'piiClass' => PiiClass::Personal]);

    expect(fn (): string => $strict->encode($personal))->toThrow(InvalidMessage::class)
        ->and(fn (): MessageEnvelope => $strict->decode(cloudEventCodec()->encode($personal)))->toThrow(InvalidMessage::class);
});
