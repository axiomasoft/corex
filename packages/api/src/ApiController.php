<?php

declare(strict_types=1);

namespace CoreX\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiController
{
    public function __construct(private readonly Dispatcher $dispatcher) {}

    public function __invoke(Request $request): JsonResponse
    {
        $version = $request->route('version');
        $path = $request->route('path');

        if (! is_string($version) || ($path !== null && ! is_string($path))) {
            throw new ApiError('operation_not_found', 404, 'Operation not found.');
        }

        return $this->dispatcher->dispatch($request, $version, $path ?? '');
    }
}
