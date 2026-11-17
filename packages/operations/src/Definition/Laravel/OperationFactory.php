<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Definition\Internal\ApiOperationValidator;
use CoreX\Operations\Definition\Internal\OperationContractHasher;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Invocation\Enums\InputEnvelope;
use LogicException;
use Packman\Modules\Data\Extenders\Api;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class OperationFactory
{
    public static function fromDeclaration(Api $api, string $registryFingerprint = ''): Operation
    {
        ApiOperationValidator::validate($api);

        if ($api->declaringModule === null || ! is_string($api->handler) || ! is_a($api->handler, OperationHandler::class, true)) {
            throw new LogicException('API operation must have compiled ownership and implement OperationHandler.');
        }

        $cost = $api->cost;

        if ($cost !== null) {
            foreach ($cost as $key => $weight) {
                if (! is_string($key) || ! is_int($weight)) {
                    throw new LogicException('Cost recipe must contain integer weights.');
                }
            }
        }

        /** @var array<string, int>|null $cost */
        return new Operation(module: $api->declaringModule, id: $api->operationId, version: $api->version, method: $api->method, path: $api->path, ability: $api->ability, handler: $api->handler, requestSchema: JsonDocuments::capture($api->requestSchema), responseSchema: JsonDocuments::capture($api->responseSchema), inputRules: $api->inputRules, successStatus: $api->successStatus, mode: OperationMode::from($api->operationMode), requiredScopes: $api->requiredScopes, idempotency: IdempotencyRequirement::from($api->idempotencyRequirement), readFields: $api->readFields, writeFields: $api->writeFields, pagination: JsonDocuments::nullable($api->pagination), capabilities: $api->capabilities, deprecation: $api->deprecation, contractHash: OperationContractHasher::hash(api: $api, registryFingerprint: $registryFingerprint), effectMode: $api->effectMode, executionSupported: $api->executionSupported, cost: $cost, inputEnvelope: InputEnvelope::forMethod($api->method));
    }
}
