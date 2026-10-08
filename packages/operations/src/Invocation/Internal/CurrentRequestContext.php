<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\RequestContextProvider;
use Illuminate\Contracts\Container\Container;
use Override;

/** @internal Holds no request state; reads the active Laravel request on every call. */
final readonly class CurrentRequestContext implements RequestContextProvider
{
    public function __construct(private Container $container) {}

    #[Override]
    public function current(): RequestContext
    {
        $request = $this->container->make('request');
        $context = $request->attributes->get(RequestContext::class);

        return $context instanceof RequestContext ? $context
            : throw new ApiError(errorCode: OperationsErrorCode::ContextRequired, status: 403, message: 'An API operation context is required.');
    }
}
