<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Data;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\JsonDocument;
use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Invocation\Enums\InputEnvelope;

/** Immutable runtime projection of one compiled declaration. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class Operation
{
    /**
     * @param  class-string<OperationHandler>  $handler
     * @param  array<string, list<string>>  $inputRules
     * @param  array<string, int>|null  $cost  Canonical compiled recipe or null for legacy defaults.
     */
    public function __construct(
        public string $module,
        public string $id,
        public string $version,
        public string $method,
        public string $path,
        public string $ability,
        public string $handler,
        public JsonDocument $requestSchema,
        public JsonDocument $responseSchema,
        public array $inputRules,
        public int $successStatus,
        public OperationMode $mode = OperationMode::Query,
        /** @var list<string> */
        public array $requiredScopes = [],
        public IdempotencyRequirement $idempotency = IdempotencyRequirement::Forbidden,
        /** @var list<string>|null */
        public ?array $readFields = null,
        /** @var list<string>|null */
        public ?array $writeFields = null,
        /** JSON value captured at the integration boundary. */
        public ?JsonDocument $pagination = null,
        /** @var list<string> */
        public array $capabilities = [],
        /** @var array<string, scalar|null>|null */
        public ?array $deprecation = null,
        public string $contractHash = '',
        public ?string $effectMode = null,
        public bool $executionSupported = true,
        public ?array $cost = null,
        public InputEnvelope $inputEnvelope = InputEnvelope::JsonObject,
    ) {}

}
