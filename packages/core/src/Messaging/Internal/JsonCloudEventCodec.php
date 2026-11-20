<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Exceptions\InvalidScopePayload;
use CoreX\Messaging\Contracts\CloudEventCodec;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Exceptions\InvalidMessage;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use JsonException;
use Override;
use Packman\Attributes\Internal;
use stdClass;

/** @phpstan-import-type EventData from CloudEventCodec */
#[Internal]
final readonly class JsonCloudEventCodec implements CloudEventCodec
{
    private const int JsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    private const array Attributes = ['specversion', 'id', 'source', 'type', 'subject', 'time', 'datacontenttype', 'dataschema', 'corexscope', 'corexpii', 'corexenvelope', 'corexproof', 'corexcorrelation', 'corexcausation', 'traceparent', 'tracestate', 'data'];

    public function __construct(private MessageContractRegistry $contracts, private DataScopeCodec $scopes) {}

    #[Override]
    public function encode(MessageEnvelope $message): string
    {
        $contract = $this->contracts->get(name: $message->name, version: $message->contractVersion);
        $contract->validateFact($message);
        $schema = $contract->dataSchema;
        self::assertMetadata($message->metadata);
        $event = self::identity($message);

        $event['subject'] = self::json($message->subjectData());

        if ($message->seal !== null) {
            $event['corexproof'] = $message->seal;
        }

        $event += self::content($message);

        if ($schema !== null) {
            $event['dataschema'] = $schema;
        }

        $event['corexscope'] = self::json((object) self::canonical($this->scopes->encode($message->scope)));
        $event['corexpii'] = $message->piiClass->value;
        $event['corexenvelope'] = $message->envelopeVersion;

        foreach (['corexcorrelation' => $message->correlationId, 'corexcausation' => $message->causationId] as $key => $value) {
            if ($value !== null) {
                $event[$key] = $value;
            }
        }

        foreach (['traceparent', 'tracestate'] as $key) {
            if (isset($message->metadata[$key])) {
                $event[$key] = $message->metadata[$key];
            }
        }

        $event['data'] = json_decode($message->payload->encode(), false, flags: JSON_THROW_ON_ERROR);

        return self::json($event);
    }

    /** @param EventData $data */
    #[Override]
    public function encodeExternal(MessageEnvelope $message, array $data): string
    {
        $this->contracts->get(name: $message->name, version: $message->contractVersion)->validateFact($message);
        self::assertMetadata($message->metadata);

        return self::json(self::identity($message) + self::content($message) + ['data' => self::objectData($data)]);
    }

    #[Override]
    public function decode(string $json): MessageEnvelope
    {
        [$message,$schema] = self::parse($json, $this->scopes);
        self::validate($message, $schema, $this->contracts);

        return $message;
    }

    public static function validate(MessageEnvelope $message, ?string $schema, MessageContractRegistry $registry): void
    {
        $contract = $registry->get($message->name, $message->contractVersion);

        if ($schema !== $contract->dataSchema) {
            throw InvalidMessage::identity('CloudEvent data schema');
        }
        $contract->validateFact($message);
    }

    /** @return array{MessageEnvelope,?string} Untrusted bytes until the consuming work frame verifies provenance. */
    public static function parse(string $json, DataScopeCodec $scopes): array
    {
        try {
            $event = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidMessage::payloadShape($e->getMessage());
        }

        if (! $event instanceof stdClass) {
            throw InvalidMessage::payloadShape('CloudEvent must be an object');
        }

        $attributes = get_object_vars($event);

        if (array_diff(array_keys($attributes), self::Attributes) !== []
            || ($attributes['specversion'] ?? null) !== '1.0'
            || ($attributes['datacontenttype'] ?? null) !== 'application/json'
            || ($attributes['corexenvelope'] ?? null) !== MessageEnvelope::ENVELOPE_VERSION
            || ! ($attributes['data'] ?? null) instanceof stdClass) {
            throw InvalidMessage::identity('CloudEvent attributes');
        }

        foreach (['id', 'source', 'type', 'time', 'corexscope', 'corexpii', 'corexcorrelation', 'subject'] as $key) {
            if (! is_string($attributes[$key] ?? null) || $attributes[$key] === '') {
                throw InvalidMessage::identity("CloudEvent {$key}");
            }
        }

        foreach (['corexproof', 'subject', 'dataschema', 'corexcorrelation', 'corexcausation', 'traceparent', 'tracestate'] as $key) {
            if (array_key_exists($key, $attributes) && (! is_string($attributes[$key]) || $attributes[$key] === '')) {
                throw InvalidMessage::identity("CloudEvent {$key}");
            }
        }

        if (preg_match('/\A([a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*){1,3})\.v([1-9][0-9]*)\z/D', $attributes['type'], $type) !== 1
            || strlen($type[2]) > strlen((string) PHP_INT_MAX)
            || (strlen($type[2]) === strlen((string) PHP_INT_MAX) && strcmp($type[2], (string) PHP_INT_MAX) > 0)) {
            throw InvalidMessage::identity('CloudEvent type');
        }

        $schema = self::optionalString(attributes: $attributes, key: 'dataschema');
        $pii = PiiClass::tryFrom($attributes['corexpii']);

        if ($pii === null) {
            throw InvalidMessage::identity('CloudEvent PII class');
        }

        try {
            $scopeObject = json_decode($attributes['corexscope'], false, 512, JSON_THROW_ON_ERROR);
            $scope = json_decode($attributes['corexscope'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidMessage::payloadShape($e->getMessage());
        }

        if (! $scopeObject instanceof stdClass || ! is_array($scope)) {
            throw InvalidMessage::identity('CloudEvent scope');
        }

        $metadata = array_intersect_key($attributes, array_flip(['traceparent', 'tracestate']));
        self::assertMetadata($metadata);

        /** @var array<string, scalar|null> $scope Object shape verified above. */
        /** @var array<string, scalar|null> $metadata String attributes verified above. */
        try {
            $decodedScope = $scopes->decode($scope);
            $subject = self::subject($attributes['subject']);
        } catch (InvalidScopePayload|InvalidArgumentException|JsonException $error) {
            throw InvalidMessage::payloadShape($error->getMessage());
        }

        $message = new MessageEnvelope(
            messageId: $attributes['id'], source: $attributes['source'], name: $type[1], contractVersion: (int) $type[2],
            occurredAt: self::time($attributes['time']), scope: $decodedScope,
            payload: MessagePayload::fromJson(self::json($attributes['data'])), piiClass: $pii,
            subject: $subject, correlationId: $attributes['corexcorrelation'],
            causationId: self::optionalString(attributes: $attributes, key: 'corexcausation'), metadata: $metadata, seal: self::optionalString($attributes, 'corexproof'),
        );

        return [$message, $schema];
    }

    public static function subject(string $encoded): RecordRef|EntityHandle
    {
        $subject = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($subject) || ! is_string($subject['entity'] ?? null)) {
            throw InvalidMessage::identity('CloudEvent subject');
        }

        if (count($subject) === 1) {
            return new EntityHandle($subject['entity']);
        }

        if (count($subject) !== 2 || ! is_string($subject['id'] ?? null)) {
            throw InvalidMessage::identity('CloudEvent subject');
        }

        return new RecordRef($subject['entity'], $subject['id']);
    }

    /** @return array<string, mixed> */
    private static function identity(MessageEnvelope $message): array
    {
        return ['specversion' => '1.0', 'id' => $message->messageId, 'source' => $message->source, 'type' => $message->contract()];
    }

    /** @return array<string, mixed> */
    private static function content(MessageEnvelope $message): array
    {
        return ['time' => $message->occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'), 'datacontenttype' => 'application/json'];
    }

    /** @param array<string, mixed> $metadata */
    public static function assertMetadata(array $metadata): void
    {
        foreach ($metadata as $key => $value) {
            if (! in_array($key, ['traceparent', 'tracestate'], true) || ! is_string($value) || $value === '' || strlen($value) > 512 || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
                throw InvalidMessage::identity('CloudEvent trace metadata');
            }
        }

        if (isset($metadata['tracestate']) && ! isset($metadata['traceparent'])) {
            throw InvalidMessage::identity('CloudEvent traceparent');
        }
    }

    /** @param array<array-key, mixed> $attributes */
    private static function optionalString(array $attributes, string $key): ?string
    {
        if (! array_key_exists($key, $attributes)) {
            return null;
        }

        $value = $attributes[$key];

        if (! is_string($value) || $value === '') {
            throw InvalidMessage::identity("CloudEvent {$key}");
        }

        return $value;
    }

    private static function time(string $value): DateTimeImmutable
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}[Tt]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:[Zz]|[+-](?:[01]\d|2[0-3]):[0-5]\d)\z/D', $value) !== 1) {
            throw InvalidMessage::identity('CloudEvent time');
        }

        try {
            $time = new DateTimeImmutable($value);
        } catch (Exception) {
            throw InvalidMessage::identity('CloudEvent time');
        }

        if (DateTimeImmutable::getLastErrors() !== false) {
            throw InvalidMessage::identity('CloudEvent time');
        }

        return $time;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function canonical(array $values, int $depth = 0): array
    {
        if ($depth >= MessagePayload::MAX_DEPTH) {
            throw InvalidMessage::payloadShape('nesting exceeds eight levels');
        }

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = self::canonical(values: $value, depth: $depth + 1);
            } elseif ($value !== null && ! is_scalar($value)) {
                throw InvalidMessage::payloadShape('contains an unsupported value');
            }
        }

        if (! array_is_list($values)) {
            ksort($values);
        }

        return $values;
    }

    /** @param array<array-key, mixed> $data */
    private static function objectData(array $data): stdClass
    {
        foreach (array_keys($data) as $key) {
            if (! is_string($key) || $key === '') {
                throw InvalidMessage::payloadShape('external data must be an object');
            }
        }

        return (object) self::canonical($data);
    }

    private static function json(mixed $value): string
    {
        try {
            return json_encode($value, self::JsonFlags);
        } catch (JsonException $e) {
            throw InvalidMessage::payloadShape($e->getMessage());
        }
    }
}
