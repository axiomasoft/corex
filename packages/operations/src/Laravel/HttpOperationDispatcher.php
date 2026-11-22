<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel;

use ArrayAccess;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Errors\Contracts\ErrorMapping;
use CoreX\Operations\Errors\Contracts\ResponseSerializer;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Internal\Http\HttpOperationResponder;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Enums\InputEnvelope;
use CoreX\Operations\Invocation\Internal\JsonObjectEnvelopeReader;
use CoreX\Operations\Invocation\Internal\QueryEnvelopeReader;
use CoreX\Operations\Invocation\Laravel\AuthenticatedOperationContext;
use CoreX\Operations\Invocation\Laravel\InvocationMetadataFactory;
use CoreX\Operations\Laravel\Contracts\HttpOperationDispatcher as Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Packman\Attributes\Internal;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

#[Internal]
final readonly class HttpOperationDispatcher implements Dispatcher
{
    public function __construct(
        private OperationRegistry $operations,
        private AuthenticatedOperationContext $contexts,
        private ResponseSerializer $serializer,
        private ErrorMapping $errors,
        private OperationInvoker $invoker,
        private HttpOperationResponder $responder,
        private ?string $etagSecret,
        private Application $container,
    ) {}

    public function dispatch(Request $request, string $version, string $path): JsonResponse
    {
        $inherited = $request->attributes->get(InvocationMetadata::class);
        $metadata = $inherited instanceof InvocationMetadata && $inherited->version === 'v2'
            ? $inherited : InvocationMetadataFactory::fromRequest(request: $request, version: 'v2');

        try {
            if ($version !== 'v2') {
                throw new ApiError(errorCode: OperationsErrorCode::OperationNotFound, status: 404, message: 'Operation not found.');
            }

            [$operation, $parameters] = $this->operations->match(version: 'v2', method: $request->method(), path: '/'.ltrim($path, '/'));
            $metadata = new InvocationMetadata(requestId: $metadata->requestId, version: 'v2', operationId: $operation->id,
                method: $request->method(), correlationId: $metadata->correlationId, accept: $metadata->accept);
            $context = $this->contexts->resolve(request: $request, metadata: $metadata);
            $input = match ($operation->inputEnvelope) {
                InputEnvelope::Query => (new QueryEnvelopeReader)->read($request),
                InputEnvelope::JsonObject => (new JsonObjectEnvelopeReader)->read($request),
            };
            $keys = $request->headers->all('Idempotency-Key');

            if (count($keys) > 1) {
                throw new ApiError(errorCode: OperationsErrorCode::IdempotencyKeyInvalid, status: 422, message: 'Supply one idempotency key.');
            }

            $invocation = new OperationInvocation(
                operation: $operation,
                context: $context,
                input: JsonDocuments::capture($input),
                parameters: $parameters,
                idempotencyKey: $keys[0] ?? null,
                clientId: $context->clientId(),
            );
            $previous = $this->container->bound('request') ? $this->container->make('request') : null;
            $this->container->instance('request', $request);

            try {
                $result = $this->invoker->invoke(invocation: $invocation);
            } finally {
                if ($previous !== null) {
                    $this->container->instance('request', $previous);
                } elseif ($this->container instanceof ArrayAccess) {
                    unset($this->container['request']);
                }
            }

            $data = JsonDocuments::array($result->data);
            $status = $operation->successStatus;

            if ($result->accepted !== null) {
                $data['operation_id'] = $result->accepted->operationRef;
                $data['status_url'] = $result->accepted->statusUri;
                $status = 202;
            }
            $response = $this->serializer->success(data: $data, status: $status, metadata: $metadata);
            $body = $response->getData(true);

            if (is_array($body) && is_array($body['meta'] ?? null)) {
                $body['meta'] = [...$body['meta'], ...JsonDocuments::map($result->metadata), 'replayed' => $result->replayed];
                $response->setData($body);
            }

            if ($result->privateWitness !== null) {
                $secret = $this->etagSecret;
                $response->headers->set('Cache-Control', 'private, no-cache');
                $response->setVary('Accept-Language', false);

                if (is_string($secret) && $secret !== '') {
                    $etag = hash_hmac('sha256', json_encode([
                        'corex-entity-metadata/2', $operation->id, $context->clientId(),
                        $context->principal()->principalType()->value, $context->principal()->principalId(),
                        $request->getLocale(), $request->headers->get('Accept-Language'),
                        $result->privateWitness, $data,
                    ], JSON_THROW_ON_ERROR), $secret);
                    $response->setEtag($etag);
                    $response->isNotModified($request);
                }

                return $response;
            }

            if ($operation->id === 'bulk-operations.show' && is_string($data['status'] ?? null)
                && BulkOperationStatus::tryFrom($data['status'])?->isTerminal() === false) {
                $response->headers->set('Retry-After', '2');
            }

            if (in_array($operation->id, ['bulk-operations.result', 'bulk-operations.failures'], true)) {
                $response->headers->set('Cache-Control', 'private, no-store');
            }

            return $this->responder->project(operation: $operation, response: $response, accepted: $result->accepted);
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
}
