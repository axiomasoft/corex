<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition;

use CoreX\Modules\Extend\Api;
use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Definition\Internal\OperationContractHasher;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use LogicException;

/** Immutable runtime projection of one compiled declaration. */
final readonly class Operation
{
    /**
     * @param  class-string<OperationHandler>  $handler
     * @param  array<string, mixed>  $requestSchema
     * @param  array<string, mixed>  $responseSchema
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
        public array $requestSchema,
        public array $responseSchema,
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
        /** @var array<string, mixed>|null */
        public ?array $pagination = null,
        /** @var list<string> */
        public array $capabilities = [],
        /** @var array<string, scalar|null>|null */
        public ?array $deprecation = null,
        public string $contractHash = '',
        public ?string $effectMode = null,
        public bool $executionSupported = true,
        public ?array $cost = null,
    ) {}

    public static function fromDeclaration(Api $api, string $registryFingerprint = ''): self
    {
        ApiOperationValidator::validate($api);

        if ($api->declaringModule === null || ! is_a($api->handler, OperationHandler::class, true)) {
            throw new LogicException('API operation must have compiled ownership and implement OperationHandler.');
        }

        return new self(
            module: $api->declaringModule,
            id: $api->operationId,
            version: $api->version,
            method: $api->method,
            path: $api->path,
            ability: $api->ability,
            handler: $api->handler,
            requestSchema: $api->requestSchema,
            responseSchema: $api->responseSchema,
            inputRules: $api->inputRules,
            successStatus: $api->successStatus,
            mode: OperationMode::from($api->operationMode),
            requiredScopes: $api->requiredScopes,
            idempotency: IdempotencyRequirement::from($api->idempotencyRequirement),
            readFields: $api->readFields,
            writeFields: $api->writeFields,
            pagination: $api->pagination,
            capabilities: $api->capabilities,
            deprecation: $api->deprecation,
            contractHash: OperationContractHasher::hash($api, $registryFingerprint),
            effectMode: $api->effectMode,
            executionSupported: $api->executionSupported,
            cost: $api->cost,
        );
    }
}
