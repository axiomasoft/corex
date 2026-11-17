<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use Closure;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Laravel\OperationFactory;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Data\JsonValue;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Operations\Validation\Laravel\JsonObjectFactory;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Contracts\ToolSchemaValidator;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolOutcome;
use CoreX\Tools\Data\ToolResult;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LogicException;
use Override;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Extenders\Api;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Guarded headless invocation over compiled tool declarations and owning handlers. */
final readonly class GuardedToolInvoker implements ToolInvoker
{
    public function __construct(
        private ModuleRegistry $modules,
        private ToolPolicy $policy,
        private ToolSchemaValidator $schemas,
        private ToolExecutionGuard $guard,
        private Container $container,
        private OperationInvoker $operations,
    ) {}

    #[Override]
    public function invoke(RequestContext $context, ToolCall $call): ToolResult
    {
        $declaration = $this->resolveDeclaration(code: $call->code, toolVersion: $call->toolVersion);

        if ($declaration === null || $declaration->declaringModule === null) {
            return $this->denied(errorCode: ToolsErrorCode::ToolUnavailable, call: $call);
        }

        if (! $this->modules->isActive(name: $declaration->declaringModule, ctx: $context->tenant())) {
            return $this->denied(errorCode: ToolsErrorCode::ToolUnavailable, call: $call);
        }

        $tool = ToolDescriptorFactory::fromDeclaration($declaration);

        if ($call->expectedContractHash !== $tool->contractHash) {
            return $this->denied(errorCode: ToolsErrorCode::ToolRevisionConflict, call: $call);
        }

        if (! $this->policy->invoke(tool: $tool, context: $context, arguments: $call->arguments)) {
            return $this->denied(errorCode: ToolsErrorCode::ToolUnavailable, call: $call);
        }

        $inputError = $this->validateInput(tool: $tool, call: $call);

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

        $result = $this->guard->execute(tool: $tool, context: $context, call: $call, effect: $effect);

        if (
            $callbackInvocations === 0
            && $result->outcome === ToolOutcome::Committed
            && ! $result->replayed
        ) {
            return $this->denied(errorCode: ToolsErrorCode::InternalError, call: $call);
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
                return $this->denied(errorCode: ToolsErrorCode::InputInvalid, call: $call);
            }
        } catch (ToolSchemaFailure $exception) {
            return $this->denied(errorCode: $exception->errorCode, call: $call);
        } catch (RuntimeException) {
            return $this->denied(errorCode: ToolsErrorCode::InternalError, call: $call);
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
                        input: JsonDocuments::capture($input),
                        parameters: $parameters,
                        idempotencyKey: $call->operationKey,
                    ));
                } catch (Throwable $error) {
                    if ($error instanceof ApiError) {
                        return $this->operationFailed(errorCode: $error->errorCode, call: $call);
                    }

                    if ($error instanceof HttpExceptionInterface && $error->getStatusCode() === 429) {
                        return $this->denied(errorCode: ToolsErrorCode::RateLimited, call: $call);
                    }

                    throw $error;
                }

                $output = JsonDocuments::array($result->data);
                $replayed = $result->replayed;
                $metadata = JsonDocuments::map($result->metadata);
            } else {
                $handler = $this->container->make($declaration->handler ?? throw new LogicException('Tool handler missing.'));

                if (! $handler instanceof OperationHandler) {
                    return $this->denied(errorCode: ToolsErrorCode::InternalError, call: $call);
                }

                $toolContext = new ToolRequestContext(
                    context: $context,
                    operationKey: $call->operationKey,
                    toolContractHash: $tool->contractHash,
                );
                $output = $handler->handle(context: $toolContext, input: $input, parameters: $parameters);
            }

            $structured = JsonObjectFactory::fromArray($output);

            $outputError = $this->validateOutput(tool: $tool, structured: $structured, call: $call);

            if ($outputError !== null) {
                return $outputError;
            }

            return new ToolResult(
                outcome: ToolOutcome::Committed,
                structuredData: $structured,
                errorCode: null,
                operationKey: $call->operationKey,
                replayed: $replayed,
                metadata: JsonDocuments::capture($metadata),
            );
        } catch (Throwable $exception) {
            if ($tool->idempotency === 'provider-key') {
                throw $exception;
            }

            return $this->denied(errorCode: ToolsErrorCode::InternalError, call: $call);
        }
    }

    private function exposedOperation(Api $declaration): Operation
    {
        $ref = $declaration->operationRef ?? throw new LogicException('Exposed tool is missing operationRef.');

        foreach ($this->modules->compiled()->api as $api) {
            if ($api->mode === 'operation' && $api->version.':'.$api->operationId === $ref) {
                return OperationFactory::fromDeclaration(api: $api, registryFingerprint: $this->modules->compiled()->fingerprint);
            }
        }

        throw new ApiError(errorCode: OperationsErrorCode::OperationNotFound, status: 404, message: 'Operation not found.');
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
                    $value = $decoded[$argumentField];

                    if (! is_scalar($value) && $value !== null) {
                        throw new InvalidArgumentException('Path arguments must be scalar.');
                    }
                    $parameters[substr($target, 5)] = (string) $value;
                } else {
                    $input[$target] = $decoded[$argumentField];
                }
            }

            return [$input, $parameters];
        }

        $named = [];
        foreach ($decoded as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Tool input keys must be named.');
            }
            $named[$key] = $value;
        }

        return [$named, []];
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
                return $this->denied(errorCode: ToolsErrorCode::OutputContractFailed, call: $call);
            }
        } catch (ToolSchemaFailure $exception) {
            return $this->denied(errorCode: $exception->errorCode, call: $call);
        } catch (RuntimeException) {
            return $this->denied(errorCode: ToolsErrorCode::InternalError, call: $call);
        }

        return null;
    }

    private function denied(ToolsErrorCode $errorCode, ToolCall $call): ToolResult
    {
        return $this->operationFailed(errorCode: $errorCode->value, call: $call);
    }

    /** Registered codes from the invoked operation retain their owning namespace. */
    private function operationFailed(string $errorCode, ToolCall $call): ToolResult
    {
        return new ToolResult(
            outcome: ToolOutcome::NotExecuted,
            structuredData: null,
            errorCode: $errorCode,
            operationKey: $call->operationKey,
            replayed: false,
        );
    }
}
