<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Modules\Data\Api;
use CoreX\Operations\Validation\Laravel\JsonObjectFactory;
use CoreX\Tools\Data\ToolDescriptor;
use LogicException;

final class ToolDescriptorFactory
{
    public static function fromDeclaration(Api $api): ToolDescriptor
    {
        if ($api->mode !== 'tool' || $api->declaringModule === null) {
            throw new LogicException('ToolDescriptor requires a compiled tool declaration.');
        }
        ToolName::assertCanonical($api->toolCode);
        ToolName::assertVersion($api->toolVersion);
        $inputSchema = JsonObjectFactory::fromSchemaArray($api->requestSchema);
        $outputSchema = JsonObjectFactory::fromSchemaArray($api->responseSchema);

        return new ToolDescriptor(module: $api->declaringModule, code: $api->toolCode, toolVersion: $api->toolVersion, wireName: ToolName::wireName(code: $api->toolCode, version: $api->toolVersion), title: $api->title, description: $api->description, ability: $api->ability, sideEffect: $api->sideEffect, idempotency: $api->idempotency, inputSchema: $inputSchema, outputSchema: $outputSchema, schemaProfile: ToolDescriptor::SCHEMA_PROFILE, contractHash: ToolContractHasher::hash($api), operationRef: $api->operationRef, availability: JsonDocuments::capture($api->availability));
    }
}
