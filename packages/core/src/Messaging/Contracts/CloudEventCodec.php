<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use CoreX\Attributes\Api;
use CoreX\Messaging\Data\MessageEnvelope;

/**
 * CloudEvents 1.0 structured JSON with internal and external profiles.
 * Projected data uses the same eight-level JSON tree boundary as message payloads, including JSON numbers.
 *
 * @phpstan-type EventLevel0 null|bool|int|float|string
 * @phpstan-type EventLevel1 null|bool|int|float|string|array<array-key, EventLevel0>
 * @phpstan-type EventLevel2 null|bool|int|float|string|array<array-key, EventLevel1>
 * @phpstan-type EventLevel3 null|bool|int|float|string|array<array-key, EventLevel2>
 * @phpstan-type EventLevel4 null|bool|int|float|string|array<array-key, EventLevel3>
 * @phpstan-type EventLevel5 null|bool|int|float|string|array<array-key, EventLevel4>
 * @phpstan-type EventLevel6 null|bool|int|float|string|array<array-key, EventLevel5>
 * @phpstan-type EventLevel7 null|bool|int|float|string|array<array-key, EventLevel6>
 * @phpstan-type EventData array<string, EventLevel7>
 */
#[Api(since: '1.0')]
interface CloudEventCodec
{
    public function encode(MessageEnvelope $message): string;

    /** @param EventData $data Public projected data; internal schema and metadata are omitted. */
    public function encodeExternal(MessageEnvelope $message, array $data): string;

    /** Decode the internal profile, including its required CoreX extensions. */
    public function decode(string $json): MessageEnvelope;
}
