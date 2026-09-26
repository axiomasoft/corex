<?php

declare(strict_types=1);

namespace CoreX\Api;

use CoreX\Api\Contracts\ContextResolver;
use CoreX\Api\Contracts\OperationInvoker;
use CoreX\Api\Contracts\PrincipalResolver;
use CoreX\Api\Contracts\ResponseSerializer;
use CoreX\Api\Data\OperationInvocation;
use CoreX\Api\Internal\ErrorMapper;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class Dispatcher
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
        $inherited = $request->attributes->get(RequestMetadata::class);
        $metadata = $inherited instanceof RequestMetadata ? $inherited : RequestMetadata::fromRequest($request, $version);
        $requestId = $metadata->requestId;

        try {
            [$operation, $parameters] = $this->operations->match($version, $request->method(), '/'.ltrim($path, '/'));
            $metadata = new RequestMetadata($requestId, $version, $operation->id, $request->method(), $metadata->correlationId, $metadata->accept);
            $principal = $this->principals->resolve($request)
                ?? throw new ApiError('authentication_required', 401, 'Authentication is required.');
            $context = $this->contexts->resolve($request, $principal, $metadata);
            $input = $this->input($request, $operation);
            $previousRequest = $this->container->bound('request') ? $this->container->make('request') : null;
            $this->container->instance('request', $request);

            try {
                $result = $this->invoker->invoke(new OperationInvocation(
                    operation: $operation,
                    context: $context,
                    input: $input,
                    parameters: $parameters,
                    idempotencyKey: null,
                ));
            } finally {
                if ($previousRequest !== null) {
                    $this->container->instance('request', $previousRequest);
                }
            }

            $response = $this->serializer->success($result->data, $operation->successStatus, $metadata);

            if ($result->metadata !== []) {
                $body = $response->getData(true);
                $body['meta'] = [...$body['meta'], ...$result->metadata];
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
                throw new ApiError('validation_failed', 422, 'A JSON object body without query parameters is required.');
            }

            try {
                $body = $request->getContent();
                $input = json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new ApiError('validation_failed', 422, 'Malformed JSON body.');
            }

            if (! is_array($input) || ! str_starts_with(ltrim($body), '{')) {
                throw new ApiError('validation_failed', 422, 'A JSON object body is required.');
            }
        }

        $allowed = array_unique(array_map(static fn (string $key): string => explode('.', $key)[0], array_keys($operation->inputRules)));

        if (array_diff(array_keys($input), $allowed) !== []) {
            throw new ApiError('validation_failed', 422, 'Input contains undeclared fields.');
        }

        return $this->validation->make($input, $operation->inputRules)->validate();
    }
}
