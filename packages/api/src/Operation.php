<?php

declare(strict_types=1);

namespace CoreX\Api;

use CoreX\Api\Contracts\OperationHandler;
use CoreX\Modules\Compiler\ApiOperationValidator;
use CoreX\Modules\Extend\Api;
use LogicException;

/** Immutable runtime projection of one compiled declaration. */
final readonly class Operation
{
    /**
     * @param  class-string<OperationHandler>  $handler
     * @param  array<string, mixed>  $requestSchema
     * @param  array<string, mixed>  $responseSchema
     * @param  array<string, list<string>>  $inputRules
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
    ) {}

    public static function fromDeclaration(Api $api): self
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
        );
    }
}
