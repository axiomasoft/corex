<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use Closure;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Extend\Api;
use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Definition\Operation;
use CoreX\Operations\Errors\ApiError;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Data\JsonValue;
use CoreX\Operations\Validation\JsonCanonical;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Contracts\ToolSchemaValidator;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolResult;
use Illuminate\Contracts\Container\Container;
use LogicException;
use Override;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Guarded headless invocation over compiled tool declarations and owning handlers. */
final class GuardedToolInvoker implements ToolInvoker
{
    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly ToolPolicy $policy,
        private readonly ToolSchemaValidator $schemas,
        private readonly ToolExecutionGuard $guard,
        private readonly Container $container,
        private readonly OperationInvoker $operations,
    ) {}

    #[Override]
    public function invoke(RequestContext $context, ToolCall $call): ToolResult
    {
        $declaration = $this->resolveDeclaration(code: $call->code, toolVersion: $call->toolVersion);

        if ($declaration === null || $declaration->declaringModule === null) {
            return $this->denied('tool_unavailable', $call);
        }

        if (! $this->modules->isActive($declaration->declaringModule, $context->tenant())) {
            return $this->denied('tool_unavailable', $call);
        }

        $tool = ToolDescriptor::fromDeclaration($declaration);

        if ($call->expectedContractHash !== $tool->contractHash) {
            return $this->denied('tool_revision_conflict', $call);
        }

        if (! $this->policy->invoke($tool, $context, $call->arguments)) {
            return $this->denied('tool_unavailable', $call);
        }

        $inputError = $this->validateInput($tool, $call);

        if ($inputError !== null) {
            return $inputError;
        }

        $callbackInvocations = 0;

        /** @var Closure():ToolResult $effect */
        $effect = function () use (
            $declaration,
            $tool,
            $context,
            $call,
            &$callbackInvocations,
        ): ToolResult {
            $callbackInvocations++;

            if ($callbackInvocations !== 1) {
                throw new LogicException('Tool execution callback invoked more than once.');
            }

            return $this->executeHandler(
                declaration: $declaration,
                tool: $tool,
                context: $context,
                call: $call,
            );
        };

        $result = $this->guard->execute($tool, $context, $call, $effect);

        if (
            $callbackInvocations === 0
            && $result->outcome === ToolResult::OUTCOME_COMMITTED
            && ! $result->replayed
        ) {
            return $this->denied('internal_error', $call);
        }

        return $result;
    }

    private function resolveDeclaration(string $code, string $toolVersion): ?Api
    {
        foreach ($this->modules->compiled()->api as $api) {
            if ($api->mode === 'tool' && $api->toolCode === $code && $api->toolVersion === $toolVersion) {
                return $api;
            }
        }

        return null;
    }

    private function validateInput(ToolDescriptor $tool, ToolCall $call): ?ToolResult
    {
        try {
            $handle = $this->schemas->admit(schema: $tool->inputSchema, profile: $tool->schemaProfile);
            $validation = $this->schemas->validate(
                schema: $handle,
                value: new JsonValue(canonicalJson: $call->arguments->canonicalJson),
            );

            if (! $validation->valid) {
                return $this->denied('input_invalid', $call);
            }
        } catch (RuntimeException $exception) {
            $code = match ($exception->getMessage()) {
                'schema_unavailable', 'schema_limit' => $exception->getMessage(),
                default => 'internal_error',
            };

            return $this->denied($code, $call);
        }

        return null;
    }

    private function executeHandler(
        Api $declaration,
        ToolDescriptor $tool,
        RequestContext $context,
        ToolCall $call,
    ): ToolResult {
        try {
            [$input, $parameters] = $this->mapArguments(declaration: $declaration, arguments: $call->arguments);

            $replayed = false;
            $metadata = [];

            if ($declaration->toolKind === 'exposed') {
                try {
                    $result = $this->operations->invoke(new OperationInvocation(
                        operation: $this->exposedOperation($declaration),
                        context: $context,
                        input: $input,
                        parameters: $parameters,
                        idempotencyKey: $call->operationKey,
                    ));
                } catch (Throwable $error) {
                    if ($error instanceof ApiError) {
                        return $this->denied($error->errorCode, $call);
                    }

                    if ($error instanceof HttpExceptionInterface && $error->getStatusCode() === 429) {
                        return $this->denied('rate_limited', $call);
                    }

                    throw $error;
                }

                $output = $result->data;
                $replayed = $result->replayed;
                $metadata = $result->metadata;
            } else {
                $handler = $this->container->make($declaration->handler ?? throw new LogicException('Tool handler missing.'));

                if (! $handler instanceof OperationHandler) {
                    return $this->denied('internal_error', $call);
                }

                $toolContext = new ToolRequestContext(
                    context: $context,
                    operationKey: $call->operationKey,
                    toolContractHash: $tool->contractHash,
                );
                $output = $handler->handle(context: $toolContext, input: $input, parameters: $parameters);
            }

            $structured = JsonObject::fromArray($output);

            $outputError = $this->validateOutput(tool: $tool, structured: $structured, call: $call);

            if ($outputError !== null) {
                return $outputError;
            }

            return new ToolResult(
                outcome: ToolResult::OUTCOME_COMMITTED,
                structuredData: $structured,
                errorCode: null,
                operationKey: $call->operationKey,
                replayed: $replayed,
                metadata: $metadata,
            );
        } catch (Throwable $exception) {
            if ($tool->idempotency === 'provider-key') {
                throw $exception;
            }

            return $this->denied('internal_error', $call);
        }
    }

    private function exposedOperation(Api $declaration): Operation
    {
        $ref = $declaration->operationRef ?? throw new LogicException('Exposed tool is missing operationRef.');

        foreach ($this->modules->compiled()->api as $api) {
            if ($api->mode === 'operation' && $api->version.':'.$api->operationId === $ref) {
                return Operation::fromDeclaration($api, $this->modules->compiled()->fingerprint);
            }
        }

        throw new ApiError('operation_not_found', 404, 'Operation not found.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function mapArguments(Api $declaration, JsonObject $arguments): array
    {
        $decoded = JsonCanonical::decodeObject($arguments->canonicalJson) ?? [];

        if ($declaration->toolKind === 'exposed' && $declaration->parameterMap !== []) {
            $input = [];
            $parameters = [];

            foreach ($declaration->parameterMap as $argumentField => $target) {
                if (! array_key_exists($argumentField, $decoded)) {
                    continue;
                }

                if (str_starts_with($target, 'path:')) {
                    $parameters[substr($target, 5)] = (string) $decoded[$argumentField];
                } else {
                    $input[$target] = $decoded[$argumentField];
                }
            }

            return [$input, $parameters];
        }

        return [$decoded, []];
    }

    private function validateOutput(ToolDescriptor $tool, JsonObject $structured, ToolCall $call): ?ToolResult
    {
        try {
            $handle = $this->schemas->admit(schema: $tool->outputSchema, profile: $tool->schemaProfile);
            $validation = $this->schemas->validate(
                schema: $handle,
                value: new JsonValue(canonicalJson: $structured->canonicalJson),
            );

            if (! $validation->valid) {
                return new ToolResult(
                    outcome: ToolResult::OUTCOME_NOT_EXECUTED,
                    structuredData: null,
                    errorCode: 'output_contract_failed',
                    operationKey: $call->operationKey,
                    replayed: false,
                );
            }
        } catch (RuntimeException $exception) {
            $code = match ($exception->getMessage()) {
                'schema_unavailable', 'schema_limit' => $exception->getMessage(),
                default => 'internal_error',
            };

            return $this->denied($code, $call);
        }

        return null;
    }

    private function denied(string $errorCode, ToolCall $call): ToolResult
    {
        return new ToolResult(
            outcome: ToolResult::OUTCOME_NOT_EXECUTED,
            structuredData: null,
            errorCode: $errorCode,
            operationKey: $call->operationKey,
            replayed: false,
        );
    }
}
