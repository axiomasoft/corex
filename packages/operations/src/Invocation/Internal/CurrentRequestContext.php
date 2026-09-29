<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use CoreX\Operations\Errors\ApiError;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Contracts\RequestContextProvider;
use Illuminate\Contracts\Container\Container;
use Override;

/** @internal Holds no request state; reads the active Laravel request on every call. */
final class CurrentRequestContext implements RequestContextProvider
{
    public function __construct(private readonly Container $container) {}

    #[Override]
    public function current(): RequestContext
    {
        $request = $this->container->make('request');
        $context = $request->attributes->get(RequestContext::class);

        return $context instanceof RequestContext ? $context
            : throw new ApiError('context_required', 403, 'An API operation context is required.');
    }
}
