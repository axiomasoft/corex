<?php

declare(strict_types=1);

namespace CoreX\Operations\Internal\Http;

use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\OperationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Packman\Attributes\Internal;

#[Internal]
final readonly class ApiController
{
    public function __construct(
        private OperationDispatcher $dispatcher,
        private OperationRegistry $operations,
        private HttpOperationResponder $responder,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $version = $request->route('version');
        $path = $request->route('path');

        if (! is_string($version) || ($path !== null && ! is_string($path))) {
            throw new ApiError(errorCode: OperationsErrorCode::OperationNotFound, status: 404, message: 'Operation not found.');
        }

        $response = $this->dispatcher->dispatch(request: $request, version: $version, path: $path ?? '');
        $operation = null;

        try {
            [$operation] = $this->operations->match(version: $version, method: $request->method(), path: '/'.ltrim($path ?? '', '/'));
        } catch (ApiError) {
            $operation = null;
        }

        return $this->responder->project(operation: $operation, response: $response);
    }
}
