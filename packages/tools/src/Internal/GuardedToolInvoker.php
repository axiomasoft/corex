<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use Closure;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Errors\Contracts\ErrorMapping;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Errors\Laravel\ProblemProjection;
use CoreX\Operations\Invocation\Contracts\ClientRequestContext;
use CoreX\Operations\Invocation\Contracts\OperationInputAdmission;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Data\JsonValue;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Operations\Validation\Laravel\JsonObjectFactory;
use CoreX\Operations\Validation\Laravel\SchemaBudget;
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
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\DescribesError;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Extenders\Api;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Guarded headless invocation over compiled tool declarations and owning handlers. */
#[Internal]
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

        if ($inputError !== null && ($tool->operationRef === null || $inputError->errorCode !== ToolsErrorCode::InputInvalid->value)) {
            return $inputError;
        }

        $callbackInvocations = 0;
        $effectResult = null;

        /** @var Closure():ToolResult $effect */
        $effect = function () use (
            $declaration,
            $tool,
            $context,
            $call,
            $inputError,
            &$callbackInvocations,
            &$effectResult,
        ): ToolResult {
            $callbackInvocations++;

            if ($callbackInvocations !== 1) {
                throw new LogicException('Tool execution callback invoked more than once.');
            }

            return $effectResult = $this->executeHandler(
                declaration: $declaration,
                tool: $tool,
                context: $context,
                call: $call,
                inputError: $inputError,
            );
        };

        try {
            $result = $this->guard->execute(tool: $tool, context: $context, call: $call, effect: $effect);
        } catch (ApiError $error) {
            // A host transaction can commit after the operation callback has returned.
            if ($tool->operationRef === null || $error->errorCode !== OperationsErrorCode::ReconciliationRequired->value) {
                throw $error;
            }

            return $this->failedOperation(error: $error, call: $call);
        } catch (Throwable $error) {
            if ($tool->operationRef === null || $callbackInvocations !== 1
                || $effectResult?->outcome !== ToolOutcome::Committed || $error instanceof DescribesError) {
                throw $error;
            }

            return $this->failedOperation(error: new ApiError(
                errorCode: OperationsErrorCode::ReconciliationRequired,
                status: 409,
                message: 'The operation outcome must be reconciled before retry.',
            ), call: $call);
        }

        if (
            $result->outcome === ToolOutcome::Committed
            && (($tool->operationRef !== null && $result !== $effectResult)
                || ($callbackInvocations === 0 && ! $result->replayed))
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

            if ($tool->operationRef !== null) {
                $json = $call->arguments->canonicalJson;

                try {
                    SchemaBudget::assertPayload(rawJson: $json, decoded: JsonCanonical::decode($json));
                } catch (InvalidArgumentException) {
                    return $this->denied(errorCode: ToolsErrorCode::SchemaLimit, call: $call);
                }
            }

            $validation = $this->schemas->validate(
                schema: $handle,
                value: new JsonValue(canonicalJson: $call->arguments->canonicalJson),
            );

            if (! $validation->valid) {
                return $this->denied(errorCode: ToolsErrorCode::InputInvalid, call: $call);
            }

            if ($tool->operationRef !== null) {
                $this->schemas->admit(schema: $tool->outputSchema, profile: $tool->schemaProfile);
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
        ?ToolResult $inputError,
    ): ToolResult {
        try {
            $replayed = false;
            $accepted = null;
            $metadata = [];

            if ($declaration->toolKind === 'exposed') {
                try {
                    [$input, $parameters] = $this->mapArguments(declaration: $declaration, arguments: $call->arguments);
                    $operation = $this->exposedOperation($declaration);
                    $invocation = new OperationInvocation(
                        operation: $operation,
                        context: $context,
                        input: JsonDocuments::capture($input),
                        parameters: $parameters,
                        idempotencyKey: $call->operationKey,
                        clientId: $context instanceof ClientRequestContext ? $context->clientId() : null,
                    );

                    if ($inputError !== null) {
                        if ($this->operations instanceof OperationInputAdmission) {
                            $this->operations->assertInput($invocation);
                        }

                        return $inputError;
                    }
                    $result = $this->operations->invoke($invocation);
                } catch (Throwable $error) {
                    return $this->failedOperation(error: $error, call: $call);
                }

                $output = JsonDocuments::array($result->data);
                $accepted = $result->accepted;

                if ($accepted !== null) {
                    $output['operation_id'] = $accepted->operationRef;
                    $output['status_url'] = $accepted->statusUri;
                }
                $replayed = $result->replayed;
                $metadata = JsonDocuments::map($result->metadata);
            } else {
                [$input, $parameters] = $this->mapArguments(declaration: $declaration, arguments: $call->arguments);
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
                accepted: $accepted,
            );
        } catch (Throwable $exception) {
            if ($tool->idempotency === 'provider-key') {
                throw $exception;
            }

            return $this->denied(errorCode: ToolsErrorCode::InternalError, call: $call);
        }
    }

    private function failedOperation(Throwable $error, ToolCall $call): ToolResult
    {
        if (! $this->container->bound(ErrorMapping::class)) {
            throw $error;
        }
        $problem = $this->container->make(ErrorMapping::class)->map($error);
        $body = $this->container->make(ProblemProjection::class)->document(error: $problem);

        if ($problem->status === 429 && $error instanceof HttpExceptionInterface) {
            $seconds = filter_var($error->getHeaders()['Retry-After'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($seconds !== false) {
                $body['retry_after_seconds'] = $seconds;
            }
        }

        $outcome = $problem->errorCode === OperationsErrorCode::ReconciliationRequired->value
            ? ToolOutcome::Unknown : ToolOutcome::NotExecuted;

        return new ToolResult(outcome: $outcome, structuredData: null,
            errorCode: $problem->errorCode, operationKey: $call->operationKey, replayed: false,
            problem: new JsonDocument(json_encode($body, JSON_THROW_ON_ERROR)));
    }

    private function exposedOperation(Api $declaration): Operation
    {
        $ref = $declaration->operationRef ?? throw new LogicException('Exposed tool is missing operationRef.');

        $operations = $this->container->make(OperationRegistry::class);
        foreach ($operations->all(version: 'v2') as $operation) {
            if ($operation->module === $declaration->declaringModule && 'v2:'.$operation->id === $ref) {
                return $operation;
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
            $extra = array_map(strval(...), array_keys(array_diff_key($decoded, $declaration->parameterMap)));

            if ($extra !== []) {
                sort($extra, SORT_STRING);

                throw new ApiError(errorCode: OperationsErrorCode::UndeclaredFields, status: 422,
                    message: 'Input contains undeclared fields.', details: JsonDocuments::capture(['fields' => $extra]),
                    path: '/'.str_replace(['~', '/'], ['~0', '~1'], $extra[0]));
            }

            foreach ($declaration->parameterMap as $argumentField => $target) {
                if (! array_key_exists($argumentField, $decoded)) {
                    continue;
                }

                if (str_starts_with($target, 'path:')) {
                    $value = $decoded[$argumentField];

                    $parameter = substr($target, 5);

                    if (! is_string($value) && ! is_int($value)) {
                        throw new ApiError(errorCode: OperationsErrorCode::InvalidRequest, status: 422,
                            message: 'Invalid operation parameter.', path: '/'.$parameter);
                    }
                    $parameters[$parameter] = (string) $value;
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
        return new ToolResult(
            outcome: ToolOutcome::NotExecuted,
            structuredData: null,
            errorCode: $errorCode->value,
            operationKey: $call->operationKey,
            replayed: false,
        );
    }
}
