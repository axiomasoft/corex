<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Laravel;

use Closure;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Errors\Contracts\ErrorMapping;
use CoreX\Operations\Errors\Contracts\ResponseSerializer;
use CoreX\Operations\Invocation\Laravel\InvocationMetadataFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Throwable;

/** Application opts into placing this first, before trusted tenancy/auth/workspace middleware. */
#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final readonly class OperationErrorBoundary
{
    public function __construct(
        private ResponseSerializer $serializer,
        private ErrorMapping $errors,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $metadata = InvocationMetadataFactory::fromRequest(request: $request, version: 'v2');
        $previous = $request->attributes->get(InvocationMetadata::class);
        $request->attributes->set(InvocationMetadata::class, $metadata);

        try {
            $response = $next($request);

            // Laravel's Routing\Pipeline renders exceptions inside each slice,
            // before an outer middleware can catch them, and calls withException().
            if (($response instanceof Response || $response instanceof JsonResponse) && $response->exception instanceof Throwable) {
                return $this->serializer->error(error: $this->errors->map(error: $response->exception, reportUnexpected: false), metadata: $metadata);
            }

            return $response;
        } catch (Throwable $error) {
            return $this->serializer->error(error: $this->errors->map($error), metadata: $metadata);
        } finally {
            if ($previous === null) {
                $request->attributes->remove(InvocationMetadata::class);
            } else {
                $request->attributes->set(InvocationMetadata::class, $previous);
            }
        }
    }
}
