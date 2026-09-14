<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use CoreX\Api\ApiError;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\RequestContextProvider;
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
