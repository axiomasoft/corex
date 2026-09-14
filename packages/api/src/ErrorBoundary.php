<?php

declare(strict_types=1);

namespace CoreX\Api;

use Closure;
use CoreX\Api\Contracts\ResponseSerializer;
use CoreX\Api\Internal\ErrorMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/** Application opts into placing this first, before trusted tenancy/auth/workspace middleware. */
final class ErrorBoundary
{
    public function __construct(
        private readonly ResponseSerializer $serializer,
        private readonly ErrorMapper $errors,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $version = $request->route('version');
        $metadata = RequestMetadata::fromRequest($request, is_string($version) ? $version : 'v1');
        $previous = $request->attributes->get(RequestMetadata::class);
        $request->attributes->set(RequestMetadata::class, $metadata);

        try {
            $response = $next($request);

            // Laravel's Routing\Pipeline renders exceptions inside each slice,
            // before an outer middleware can catch them, and calls withException().
            if (($response instanceof Response || $response instanceof JsonResponse) && $response->exception instanceof Throwable) {
                return $this->serializer->error($this->errors->map($response->exception, reportUnexpected: false), $metadata);
            }

            return $response;
        } catch (Throwable $error) {
            return $this->serializer->error($this->errors->map($error), $metadata);
        } finally {
            if ($previous === null) {
                $request->attributes->remove(RequestMetadata::class);
            } else {
                $request->attributes->set(RequestMetadata::class, $previous);
            }
        }
    }
}
