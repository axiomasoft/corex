<?php

declare(strict_types=1);

namespace CoreX\Api;

use CoreX\Api\Contracts\ContextResolver;
use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\PrincipalResolver;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\ResponseSerializer;
use CoreX\Api\Internal\ErrorMapper;
use CoreX\Modules\Contracts\ModuleRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Throwable;

final class Dispatcher
{
    public function __construct(
        private readonly OperationRegistry $operations,
        private readonly ModuleRegistry $modules,
        private readonly PrincipalResolver $principals,
        private readonly ContextResolver $contexts,
        private readonly OperationAuthorizer $authorizer,
        private readonly ResponseSerializer $serializer,
        private readonly Factory $validation,
        private readonly Container $container,
        private readonly ErrorMapper $errors,
    ) {}

    /** The application explicitly mounts this boundary after its trusted tenancy/auth pipeline. */
    public function dispatch(Request $request, string $version, string $path): JsonResponse
    {
        $inherited = $request->attributes->get(RequestMetadata::class);
        $metadata = $inherited instanceof RequestMetadata ? $inherited : RequestMetadata::fromRequest($request, $version);
        $requestId = $metadata->requestId;

        try {
            [$operation, $parameters] = $this->operations->match($version, $request->method(), '/'.ltrim($path, '/'));
            $metadata = new RequestMetadata($requestId, $version, $operation->id, $request->method());
            $principal = $this->principals->resolve($request)
                ?? throw new ApiError('authentication_required', 401, 'Authentication is required.');
            $context = $this->contexts->resolve($request, $principal, $metadata);

            if (! $this->modules->isActive($operation->module, $context->tenant())) {
                throw new ApiError('module_unavailable', 404, 'Operation not found.');
            }

            if (! $this->authorizer->allows($operation, $context)) {
                throw new ApiError('forbidden', 403, 'Operation is not permitted.');
            }

            $input = $this->input($request, $operation);
            $previousContext = $request->attributes->get(RequestContext::class);
            $request->attributes->set(RequestContext::class, $context);

            try {
                $handler = $this->container->make($operation->handler);

                if (! $handler instanceof OperationHandler) {
                    throw new ApiError('internal_error', 500, 'Operation could not be completed.');
                }

                return $this->serializer->success($handler->handle($context, $input, $parameters), $operation->successStatus, $metadata);
            } finally {
                if ($previousContext === null) {
                    $request->attributes->remove(RequestContext::class);
                } else {
                    $request->attributes->set(RequestContext::class, $previousContext);
                }
            }
        } catch (Throwable $error) {
            return $this->serializer->error($this->errors->map($error), $metadata);
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
