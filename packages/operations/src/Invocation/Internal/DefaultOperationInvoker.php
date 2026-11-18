<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use Closure;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Laravel\DeclaredFieldAuthorizer;
use CoreX\Operations\Cost\Contracts\BudgetExecution as BudgetAdmission;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Data\OperationResult;
use Illuminate\Contracts\Container\Container;
use LogicException;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

/** Transport-neutral activation, authorization and exactly-once handler call. */
#[Internal]
final readonly class DefaultOperationInvoker implements OperationInvoker
{
    public function __construct(
        private ModuleRegistry $modules,
        private ClientScopeAuthorizer $clientScopes,
        private OperationAuthorizer $authorizer,
        private OperationFieldAuthorizer $fields,
        private Container $container,
        private ?BudgetAdmission $budget = null,
    ) {}

    public function invoke(OperationInvocation $invocation): OperationResult
    {
        $operation = $invocation->operation;

        if (! $operation->executionSupported) {
            throw new ApiError(errorCode: OperationsErrorCode::OperationUnsupported, status: 404, message: 'Operation is not executable.');
        }

        if (! $this->modules->isActive(name: $operation->module, ctx: $invocation->context->tenant())) {
            throw new ApiError(errorCode: OperationsErrorCode::ModuleUnavailable, status: 404, message: 'Operation not found.');
        }

        if (! $this->clientScopes->allows(operation: $operation, context: $invocation->context)) {
            throw new ApiError(errorCode: OperationsErrorCode::ScopeRequired, status: 403, message: 'The client lacks a required operation scope.');
        }

        if (! $this->authorizer->allows(operation: $operation, context: $invocation->context)) {
            throw new ApiError(errorCode: OperationsErrorCode::AccessDenied, status: 403, message: 'Operation is not permitted.');
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
                    throw new ApiError(errorCode: CoreErrorCode::InternalError, status: 500, message: 'Operation could not be completed.');
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
                : $this->budget->invoke(invocation: $invocation, effect: $effect);
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
