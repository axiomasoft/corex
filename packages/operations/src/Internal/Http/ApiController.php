<?php

declare(strict_types=1);

namespace CoreX\Operations\Internal\Http;

use CoreX\Operations\Definition\OperationRegistry;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\OperationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiController
{
    public function __construct(
        private readonly OperationDispatcher $dispatcher,
        private readonly OperationRegistry $operations,
        private readonly HttpOperationResponder $responder,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $version = $request->route('version');
        $path = $request->route('path');

        if (! is_string($version) || ($path !== null && ! is_string($path))) {
            throw new ApiError('operation_not_found', 404, 'Operation not found.');
        }

        $response = $this->dispatcher->dispatch($request, $version, $path ?? '');
        $operation = null;

        try {
            [$operation] = $this->operations->match($version, $request->method(), '/'.ltrim($path ?? '', '/'));
        } catch (ApiError) {
            $operation = null;
        }

        return $this->responder->project($operation, $response);
    }
}
