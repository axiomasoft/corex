<?php

declare(strict_types=1);

namespace CoreX\Tools\Mcp\Laravel;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Errors\Contracts\ErrorMapping;
use CoreX\Operations\Errors\Contracts\ResponseSerializer;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Errors\Laravel\ProblemProjection;
use CoreX\Operations\Invocation\Contracts\ClientRequestContext;
use CoreX\Operations\Invocation\Laravel\AuthenticatedOperationContext;
use CoreX\Operations\Invocation\Laravel\InvocationMetadataFactory;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Operations\Validation\Laravel\JsonObjectFactory;
use CoreX\Tools\Contracts\ToolCatalogue;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolOutcome;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Errors\Contracts\ProblemRegistry;
use Packman\Errors\Exceptions\ProblemRegistryException;
use stdClass;
use Throwable;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final readonly class McpToolEndpoint
{
    private const string PROTOCOL = '2025-11-25';

    public function __construct(
        private AuthenticatedOperationContext $contexts,
        private ToolCatalogue $catalogue,
        private ToolInvoker $invoker,
        private OperationRegistry $operations,
        private ClientScopeAuthorizer $clients,
        private OperationAuthorizer $actions,
        private ErrorMapping $errors,
        private ResponseSerializer $serializer,
        private ProblemRegistry $problems,
        private ProblemProjection $projection,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $metadata = InvocationMetadataFactory::fromRequest(request: $request, version: 'v2');

        try {
            $context = $this->contexts->resolve(request: $request, metadata: $metadata);

            try {
                $content = $request->getContent();
                JsonCanonical::assertWellFormed(json: $content);
                $envelope = json_decode($content);
            } catch (InvalidArgumentException) {
                return $this->error(id: null, code: -32700, message: 'Parse error.');
            }

            if (! $envelope instanceof stdClass) {
                return $this->error(id: null, code: -32600, message: 'Invalid request.');
            }
            $envelope = (array) $envelope;
            $id = $envelope['id'] ?? null;
            $method = $envelope['method'] ?? null;
            $params = $envelope['params'] ?? (object) [];

            if (($envelope['jsonrpc'] ?? null) !== '2.0' || ! is_string($method)
                || array_diff(array_keys($envelope), ['jsonrpc', 'id', 'method', 'params']) !== []
                || (! is_int($id) && ! is_string($id) && $id !== null) || ! $params instanceof stdClass) {
                return $this->error(id: null, code: -32600, message: 'Invalid request.');
            }

            if ($method === 'notifications/initialized' && $id === null) {
                $response = new JsonResponse(status: 202);
                $response->setContent('');

                return $response;
            }

            if ($id === null) {
                return $this->error(id: null, code: -32600, message: 'A request ID is required.');
            }

            return match ($method) {
                'initialize' => $this->result(id: $id, result: [
                    'protocolVersion' => self::PROTOCOL,
                    'capabilities' => ['tools' => (object) []],
                    'serverInfo' => ['name' => 'CoreX Tools', 'version' => '2.0.0'],
                ]),
                'tools/list' => $this->result(id: $id, result: ['tools' => $this->tools($context)]),
                'tools/call' => $this->call(context: $context, id: $id, params: (array) $params),
                default => $this->error(id: $id, code: -32601, message: 'Method not found.'),
            };
        } catch (Throwable $error) {
            return $this->serializer->error(error: $this->errors->map($error), metadata: $metadata);
        }
    }

    /** @return list<array<string,mixed>> */
    private function tools(ClientRequestContext $context): array
    {
        $tools = [];
        foreach ($this->catalogue->discover($context)->descriptors as $descriptor) {
            $operation = $this->operation($descriptor);

            if (! $this->visible(context: $context, descriptor: $descriptor, operation: $operation)) {
                continue;
            }
            $tools[] = [
                'name' => $descriptor->wireName,
                'description' => $descriptor->description !== '' ? $descriptor->description : $descriptor->title,
                'annotations' => [
                    'readOnlyHint' => $descriptor->sideEffect === 'read',
                    'destructiveHint' => $descriptor->sideEffect === 'irreversible',
                    'idempotentHint' => in_array($descriptor->idempotency, ['read-only', 'domain-key', 'provider-key'], true),
                    'openWorldHint' => (bool) (JsonDocuments::map(value: $descriptor->availability)['openWorld'] ?? false),
                ],
                'inputSchema' => JsonCanonical::decodeObject($descriptor->inputSchema->canonicalJson),
                'outputSchema' => JsonCanonical::decodeObject($descriptor->outputSchema->canonicalJson),
                'canonicalCode' => $descriptor->code,
                'toolVersion' => $descriptor->toolVersion,
                'contractHash' => $descriptor->contractHash,
                ...($operation === null ? [] : [
                    'operationRef' => $descriptor->operationRef,
                    'operationContractHash' => $operation->contractHash,
                ]),
            ];
        }

        return $tools;
    }

    /** @param array<array-key,mixed> $params */
    private function call(ClientRequestContext $context, int|string $id, array $params): JsonResponse
    {
        $name = $params['name'] ?? null;
        $args = $params['arguments'] ?? (object) [];
        $key = $params['operationKey'] ?? null;

        if (! is_string($name) || ! $args instanceof stdClass || ($key !== null && ! is_string($key))
            || array_diff(array_keys($params), ['name', 'arguments', 'operationKey']) !== []) {
            return $this->error(id: $id, code: -32602, message: 'Invalid tool parameters.');
        }
        $descriptor = array_find($this->catalogue->discover($context)->descriptors,
            static fn (ToolDescriptor $tool): bool => $tool->wireName === $name);
        $operation = $descriptor === null ? null : $this->operation($descriptor);

        if ($descriptor === null || ($descriptor->operationRef !== null && $operation === null)) {
            return $this->error(id: $id, code: -32602, message: 'Tool unavailable.');
        }

        if ($operation !== null) {
            $context = AuthenticatedOperationContext::forOperation(context: $context, operation: $operation);
        }
        $named = [];
        foreach ((array) $args as $field => $value) {
            if (! is_string($field)) {
                return $this->error(id: $id, code: -32602, message: 'Tool arguments must be an object.');
            }
            $named[$field] = $value;
        }
        $result = $this->invoker->invoke(context: $context, call: new ToolCall(
            code: $descriptor->code, toolVersion: $descriptor->toolVersion, expectedContractHash: $descriptor->contractHash,
            arguments: JsonObjectFactory::fromArray($named), requestId: $context->metadata()->requestId, operationKey: $key,
        ));
        $isError = $result->outcome !== ToolOutcome::Committed;
        $body = $isError
            ? ($result->problem === null ? $this->toolProblem(code: $result->errorCode) : json_decode($result->problem->canonicalJson))
            : JsonCanonical::decodeObject($result->structuredData->canonicalJson ?? '{}');

        return $this->result(id: $id, result: [
            'content' => [['type' => 'text', 'text' => json_encode($body, JSON_THROW_ON_ERROR)]],
            'structuredContent' => (object) $body,
            'isError' => $isError,
            'replayed' => $result->replayed,
            'outcome' => match ($result->outcome) {
                ToolOutcome::Unknown => 'unknown',
                ToolOutcome::NotExecuted => 'not_executed',
                ToolOutcome::Committed => $result->accepted === null ? 'completed' : 'accepted',
            },
        ]);
    }

    /** @return array<string,mixed> */
    private function toolProblem(?string $code): array
    {
        try {
            $definition = $this->problems->fromWire(code: $code ?? ToolsErrorCode::InternalError->value);
        } catch (ProblemRegistryException) {
            $definition = $this->problems->get(code: ToolsErrorCode::InternalError);
        }

        return $this->projection->document(error: new ApiError(
            errorCode: $definition->code, status: $definition->defaultStatus, message: $definition->title,
            category: $definition->category, retryable: $definition->retryable,
        ));
    }

    private function operation(ToolDescriptor $descriptor): ?Operation
    {
        return array_find($this->operations->all('v2'), static fn (Operation $operation): bool => $operation->module === $descriptor->module
            && 'v2:'.$operation->id === $descriptor->operationRef);
    }

    private function visible(ClientRequestContext $context, ToolDescriptor $descriptor, ?Operation $operation): bool
    {
        return $descriptor->operationRef === null
            || ($operation !== null && $operation->executionSupported
                && $this->clients->allows(operation: $operation, context: $context)
                && $this->actions->allows(operation: $operation, context: $context));
    }

    /** @param array<string,mixed> $result */
    private function result(int|string $id, array $result): JsonResponse
    {
        return new JsonResponse(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    private function error(int|string|null $id, int $code, string $message): JsonResponse
    {
        return new JsonResponse(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]]);
    }
}
