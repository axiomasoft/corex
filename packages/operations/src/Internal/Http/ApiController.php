<?php

declare(strict_types=1);

namespace CoreX\Operations\Internal\Http;

use CoreX\Operations\Laravel\Contracts\HttpOperationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Packman\Attributes\Internal;

#[Internal]
final readonly class ApiController
{
    public function __construct(private HttpOperationDispatcher $dispatcher) {}

    public function __invoke(Request $request): JsonResponse
    {
        $path = $request->route('path');

        $hostVersion = $request->route('version');

        return $this->dispatcher->dispatch(request: $request, version: is_string($hostVersion) ? $hostVersion : 'v2', path: is_string($path) ? $path : '');
    }
}
