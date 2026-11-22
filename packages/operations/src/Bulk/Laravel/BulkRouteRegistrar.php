<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Operations\Laravel\Contracts\HttpOperationDispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class BulkRouteRegistrar
{
    public function __construct(private HttpOperationDispatcher $dispatcher) {}

    public static function register(Application $app): void
    {
        (new BulkHttpRegistrar)->registerRoutes($app);
    }

    public function submit(Request $request): JsonResponse
    {
        return $this->dispatcher->dispatch(request: $request, version: 'v2', path: 'bulk-operations');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return $this->dispatcher->dispatch(request: $request, version: 'v2', path: 'bulk-operations/'.$id);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        return $this->dispatcher->dispatch(request: $request, version: 'v2', path: 'bulk-operations/'.$id.'/cancel');
    }

    public function result(Request $request, string $id): JsonResponse
    {
        return $this->dispatcher->dispatch(request: $request, version: 'v2', path: 'bulk-operations/'.$id.'/result');
    }

    public function failures(Request $request, string $id): JsonResponse
    {
        return $this->dispatcher->dispatch(request: $request, version: 'v2', path: 'bulk-operations/'.$id.'/failures');
    }
}
