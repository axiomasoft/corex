<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use Closure;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Laravel\DeclaredFieldAuthorizer;
use CoreX\Operations\Cost\Contracts\BudgetExecution as BudgetAdmission;
use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Data\OperationResult;
use Illuminate\Contracts\Container\Container;
use LogicException;

/** Transport-neutral activation, authorization and exactly-once handler call. */
final class DefaultOperationInvoker implements OperationInvoker
{
    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly ClientScopeAuthorizer $clientScopes,
        private readonly OperationAuthorizer $authorizer,
        private readonly OperationFieldAuthorizer $fields,
        private readonly Container $container,
        private readonly ?BudgetAdmission $budget = null,
    ) {}

    public function invoke(OperationInvocation $invocation): OperationResult
    {
        $operation = $invocation->operation;

        if (! $operation->executionSupported) {
            throw new ApiError(OperationsErrorCode::OperationUnsupported, 404, 'Operation is not executable.');
        }

        if (! $this->modules->isActive($operation->module, $invocation->context->tenant())) {
            throw new ApiError(OperationsErrorCode::ModuleUnavailable, 404, 'Operation not found.');
        }

        if (! $this->clientScopes->allows($operation, $invocation->context)) {
            throw new ApiError(OperationsErrorCode::ScopeRequired, 403, 'The client lacks a required operation scope.');
        }

        if (! $this->authorizer->allows($operation, $invocation->context)) {
            throw new ApiError(OperationsErrorCode::AccessDenied, 403, 'Operation is not permitted.');
        }

        $forbiddenWrite = DeclaredFieldAuthorizer::firstForbiddenWrite(
            operation: $operation,
            context: $invocation->context,
            authorizer: $this->fields,
            input: JsonDocuments::map($invocation->input),
        );

        if ($forbiddenWrite !== null) {
            throw new ApiError(
                errorCode: OperationsErrorCode::AccessDenied,
                status: 403,
                message: 'An input field is not writable.',
                path: $forbiddenWrite,
            );
        }

        $calls = 0;
        $previous = $this->install($invocation->context);

        try {
            $effect = function (Closure $admitHandler) use ($invocation, &$calls): OperationResult {
                $handler = $this->container->make($invocation->operation->handler);

                if (! $handler instanceof OperationHandler) {
                    throw new ApiError(CoreErrorCode::InternalError, 500, 'Operation could not be completed.');
                }

                $admitHandler();
                $calls++;

                if ($calls !== 1) {
                    throw new LogicException('Operation invocation callback invoked more than once.');
                }

                return new OperationResult(data: JsonDocuments::capture($handler->handle(
                    $invocation->context,
                    JsonDocuments::map($invocation->input),
                    $invocation->parameters,
                )));
            };

            $result = $this->budget === null
                ? $this->admitOnce(static fn (): OperationResult => $effect(static function (): void {}))
                : $this->budget->invoke($invocation, $effect);
        } finally {
            $this->restore($previous);
        }

        return new OperationResult(data: JsonDocuments::capture(DeclaredFieldAuthorizer::prune(
            operation: $operation,
            context: $invocation->context,
            authorizer: $this->fields,
            data: JsonDocuments::array($result->data),
        )), replayed: $result->replayed, metadata: JsonDocuments::capture(JsonDocuments::map($result->metadata)));
    }

    /**
     * @param  Closure():OperationResult  $callback
     */
    private function admitOnce(Closure $callback): OperationResult
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
