<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Contracts;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
interface OperationDispatcher
{
    /** The application explicitly mounts this boundary after its trusted tenancy/auth pipeline. */
    public function dispatch(Request $request, string $version, string $path): JsonResponse;
}
