<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Support;

use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Testing\HttpDependentHandler;
use CoreX\Tools\Contracts\ToolRequestContext;
use Illuminate\Contracts\Container\Container;

/** Module wrapper that adapts headless ToolRequestContext for HTTP-dependent Actions. */
final class HeadlessWrappedHandler implements OperationHandler
{
    public function __construct(
        private readonly HttpDependentHandler $inner,
        private readonly Container $container,
    ) {}

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        if (! $context instanceof ToolRequestContext) {
            return ['actor' => 'missing-tool-context'];
        }

        $request = $this->container->make('request');
        $previous = $request->attributes->get(RequestContext::class);
        $request->attributes->set(RequestContext::class, $context);

        try {
            return $this->inner->handle($context, $input, $parameters);
        } finally {
            if ($previous === null) {
                $request->attributes->remove(RequestContext::class);
            } else {
                $request->attributes->set(RequestContext::class, $previous);
            }
        }
    }
}
