<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use Closure;
use CoreX\Api\ApiError;
use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\OperationInvoker;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Data\OperationInvocation;
use CoreX\Api\Data\OperationResult;
use CoreX\Modules\Contracts\ModuleRegistry;
use Illuminate\Contracts\Container\Container;
use LogicException;

/** Transport-neutral activation, authorization and exactly-once handler call. */
final class DefaultOperationInvoker implements OperationInvoker
{
    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly OperationAuthorizer $authorizer,
        private readonly Container $container,
    ) {}

    public function invoke(OperationInvocation $invocation): OperationResult
    {
        $operation = $invocation->operation;

        if (! $operation->executionSupported) {
            throw new ApiError('operation_unsupported', 404, 'Operation is not executable.');
        }

        if (! $this->modules->isActive($operation->module, $invocation->context->tenant())) {
            throw new ApiError('module_unavailable', 404, 'Operation not found.');
        }

        if (! $this->authorizer->allows($operation, $invocation->context)) {
            throw new ApiError('forbidden', 403, 'Operation is not permitted.');
        }

        $calls = 0;
        $previous = $this->install($invocation->context);

        try {
            $data = $this->admitOnce(function () use ($invocation, &$calls): array {
                $calls++;

                if ($calls !== 1) {
                    throw new LogicException('Operation invocation callback invoked more than once.');
                }

                $handler = $this->container->make($invocation->operation->handler);

                if (! $handler instanceof OperationHandler) {
                    throw new ApiError('internal_error', 500, 'Operation could not be completed.');
                }

                return $handler->handle($invocation->context, $invocation->input, $invocation->parameters);
            });
        } finally {
            $this->restore($previous);
        }

        return new OperationResult(data: $data);
    }

    /**
     * @param  Closure(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function admitOnce(Closure $callback): array
    {
        return $callback();
    }

    private function install(RequestContext $context): mixed
    {
        if (! $this->container->bound('request')) {
            return null;
        }

        $request = $this->container->make('request');
        $previous = $request->attributes->get(RequestContext::class);
        $request->attributes->set(RequestContext::class, $context);

        return $previous;
    }

    private function restore(mixed $previous): void
    {
        if (! $this->container->bound('request')) {
            return;
        }

        $request = $this->container->make('request');

        if ($previous === null) {
            $request->attributes->remove(RequestContext::class);

            return;
        }

        $request->attributes->set(RequestContext::class, $previous);
    }
}
