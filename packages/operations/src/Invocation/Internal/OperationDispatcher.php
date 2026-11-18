<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Errors\Contracts\ErrorMapping as ErrorMapper;
use CoreX\Operations\Errors\Contracts\ResponseSerializer;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\ContextResolver;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Enums\InputEnvelope;
use CoreX\Operations\Invocation\Laravel\InvocationMetadataFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Packman\Attributes\Internal;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use UnexpectedValueException;

#[Internal]
final readonly class OperationDispatcher implements \CoreX\Operations\Invocation\Contracts\OperationDispatcher
{
    public function __construct(
        private OperationRegistry $operations,
        private PrincipalResolver $principals,
        private ContextResolver $contexts,
        private ResponseSerializer $serializer,
        private Factory $validation,
        private Container $container,
        private ErrorMapper $errors,
        private OperationInvoker $invoker,
    ) {}

    /** The application explicitly mounts this boundary after its trusted tenancy/auth pipeline. */
    public function dispatch(Request $request, string $version, string $path): JsonResponse
    {
        $inherited = $request->attributes->get(InvocationMetadata::class);
        $metadata = $inherited instanceof InvocationMetadata ? $inherited : InvocationMetadataFactory::fromRequest(request: $request, version: $version);
        $requestId = $metadata->requestId;

        try {
            [$operation, $parameters] = $this->operations->match(version: $version, method: $request->method(), path: '/'.ltrim($path, '/'));
            $metadata = new InvocationMetadata(requestId: $requestId, version: $version, operationId: $operation->id, method: $request->method(), correlationId: $metadata->correlationId, accept: $metadata->accept);
            $principal = $this->principals->resolve($request)
                ?? throw new ApiError(errorCode: OperationsErrorCode::AuthenticationRequired, status: 401, message: 'Authentication is required.');
            $context = $this->contexts->resolve(request: $request, principal: $principal, metadata: $metadata);
            $input = $this->input(request: $request, operation: $operation);
            $previousRequest = $this->container->bound('request') ? $this->container->make('request') : null;
            $this->container->instance('request', $request);

            try {
                $result = $this->invoker->invoke(new OperationInvocation(
                    operation: $operation,
                    context: $context,
                    input: JsonDocuments::capture($input),
                    parameters: $parameters,
                    idempotencyKey: $this->idempotencyKey($request),
                ));
            } finally {
                if ($previousRequest !== null) {
                    $this->container->instance('request', $previousRequest);
                }
            }

            $response = $this->serializer->success(data: JsonDocuments::array($result->data), status: $operation->successStatus, metadata: $metadata);

            if (JsonDocuments::map($result->metadata) !== []) {
                $body = $response->getData(true);

                if (! is_array($body) || ! is_array($body['meta'] ?? null)) {
                    throw new UnexpectedValueException('Success serializer must return a metadata object.');
                }

                $body['meta'] = [...$body['meta'], ...JsonDocuments::map($result->metadata)];
                $response->setData($body);
            }

            return $response;
        } catch (Throwable $error) {
            $response = $this->serializer->error(error: $this->errors->map($error), metadata: $metadata);

            if ($response->getStatusCode() === 429 && $error instanceof HttpExceptionInterface) {
                $seconds = filter_var($error->getHeaders()['Retry-After'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

                if ($seconds !== false) {
                    $response->headers->set('Retry-After', (string) $seconds);
                }
            }

            return $response;
        }
    }

    /** @return array<string, mixed> */
    private function input(Request $request, Operation $operation): array
    {
        $input = match ($operation->inputEnvelope) {
            InputEnvelope::Query => (new QueryEnvelopeReader)->read($request),
            InputEnvelope::JsonObject => (new JsonObjectEnvelopeReader)->read($request),
        };

        $allowed = array_unique(array_map(static fn (string $key): string => explode('.', $key)[0], array_keys($operation->inputRules)));

        $undeclared = array_values(array_diff(array_keys($input), $allowed));

        if ($undeclared !== []) {
            sort($undeclared);

            throw new ApiError(errorCode: OperationsErrorCode::UndeclaredFields, status: 422, message: 'Input contains undeclared fields.', details: JsonDocuments::capture(['fields' => $undeclared]));
        }

        $validated = $this->validation->make($input, $operation->inputRules)->validate();
        $named = [];

        foreach ($validated as $key => $value) {
            if (! is_string($key)) {
                throw new UnexpectedValueException('Validated input keys must be named.');
            }

            $named[$key] = $value;
        }

        return $named;
    }

    private function idempotencyKey(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key');

        return is_string($key) ? $key : null;
    }
}
