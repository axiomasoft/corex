<?php

declare(strict_types=1);

namespace CoreX\Tools\Data;

use CoreX\Modules\Extend\Api;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Tools\Internal\ToolContractHasher;
use CoreX\Tools\Internal\ToolName;
use LogicException;

/** Read projection of one compiled tool declaration. Handler refs stay internal. */
final readonly class ToolDescriptor
{
    public const SCHEMA_PROFILE = 'tool-schema/1';

    /**
     * @param  array<string, mixed>  $availability
     */
    public function __construct(
        public string $module,
        public string $code,
        public string $toolVersion,
        public string $wireName,
        public string $title,
        public string $description,
        public string $ability,
        public string $sideEffect,
        public string $idempotency,
        public JsonObject $inputSchema,
        public JsonObject $outputSchema,
        public string $schemaProfile,
        public string $contractHash,
        public ?string $operationRef,
        public array $availability,
    ) {}

    public static function fromDeclaration(Api $api): self
    {
        if ($api->mode !== 'tool' || $api->declaringModule === null) {
            throw new LogicException('ToolDescriptor requires a compiled tool declaration.');
        }

        ToolName::assertCanonical($api->toolCode);
        ToolName::assertVersion($api->toolVersion);

        $inputSchema = JsonObject::fromSchemaArray($api->requestSchema);
        $outputSchema = JsonObject::fromSchemaArray($api->responseSchema);

        return new self(
            module: $api->declaringModule,
            code: $api->toolCode,
            toolVersion: $api->toolVersion,
            wireName: ToolName::wireName($api->toolCode, $api->toolVersion),
            title: $api->title,
            description: $api->description,
            ability: $api->ability,
            sideEffect: $api->sideEffect,
            idempotency: $api->idempotency,
            inputSchema: $inputSchema,
            outputSchema: $outputSchema,
            schemaProfile: self::SCHEMA_PROFILE,
            contractHash: ToolContractHasher::hash($api),
            operationRef: $api->operationRef,
            availability: $api->availability,
        );
    }
}
