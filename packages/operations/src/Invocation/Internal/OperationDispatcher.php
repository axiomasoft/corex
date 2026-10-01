<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Errors\Contracts\ErrorMapping as ErrorMapper;
use CoreX\Operations\Errors\Contracts\ResponseSerializer;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\ContextResolver;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Laravel\InvocationMetadataFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class OperationDispatcher implements \CoreX\Operations\Invocation\Contracts\OperationDispatcher
{
    public function __construct(
        private readonly OperationRegistry $operations,
        private readonly PrincipalResolver $principals,
        private readonly ContextResolver $contexts,
        private readonly ResponseSerializer $serializer,
        private readonly Factory $validation,
        private readonly Container $container,
        private readonly ErrorMapper $errors,
        private readonly OperationInvoker $invoker,
    ) {}

    /** The application explicitly mounts this boundary after its trusted tenancy/auth pipeline. */
    public function dispatch(Request $request, string $version, string $path): JsonResponse
    {
        $inherited = $request->attributes->get(InvocationMetadata::class);
        $metadata = $inherited instanceof InvocationMetadata ? $inherited : InvocationMetadataFactory::fromRequest($request, $version);
        $requestId = $metadata->requestId;

        try {
            [$operation, $parameters] = $this->operations->match($version, $request->method(), '/'.ltrim($path, '/'));
            $metadata = new InvocationMetadata($requestId, $version, $operation->id, $request->method(), $metadata->correlationId, $metadata->accept);
            $principal = $this->principals->resolve($request)
                ?? throw new ApiError(OperationsErrorCode::AuthenticationRequired, 401, 'Authentication is required.');
            $context = $this->contexts->resolve($request, $principal, $metadata);
            $input = $this->input($request, $operation);
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

            $response = $this->serializer->success(JsonDocuments::array($result->data), $operation->successStatus, $metadata);

            if (JsonDocuments::map($result->metadata) !== []) {
                $body = $response->getData(true);
                $body['meta'] = [...$body['meta'], ...JsonDocuments::map($result->metadata)];
                $response->setData($body);
            }

            return $response;
        } catch (Throwable $error) {
            $response = $this->serializer->error($this->errors->map($error), $metadata);

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
        if ($request->method() === 'GET') {
            $input = $request->query->all();
        } else {
            if (! $request->isJson() || $request->query->count() !== 0) {
                throw new ApiError(OperationsErrorCode::ValidationFailed, 422, 'A JSON object body without query parameters is required.');
            }

            try {
                $body = $request->getContent();
                $input = json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new ApiError(OperationsErrorCode::ValidationFailed, 422, 'Malformed JSON body.');
            }

            if (! is_array($input) || ! str_starts_with(ltrim($body), '{')) {
                throw new ApiError(OperationsErrorCode::ValidationFailed, 422, 'A JSON object body is required.');
            }
        }

        $allowed = array_unique(array_map(static fn (string $key): string => explode('.', $key)[0], array_keys($operation->inputRules)));

        if (array_diff(array_keys($input), $allowed) !== []) {
            throw new ApiError(OperationsErrorCode::ValidationFailed, 422, 'Input contains undeclared fields.');
        }

        return $this->validation->make($input, $operation->inputRules)->validate();
    }

    private function idempotencyKey(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key');

        return is_string($key) ? $key : null;
    }
}
