<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use Closure;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Data\BulkOptions;
use CoreX\Operations\Bulk\Internal\BulkFailureLimit;
use CoreX\Operations\Bulk\Internal\BulkOperations;
use CoreX\Operations\Cost\Exceptions\BudgetRateLimited;
use CoreX\Operations\Cost\Exceptions\BudgetStoreUnavailable;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Invocation\Contracts\ContextResolver;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Laravel\InvocationMetadataFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class BulkRouteRegistrar
{
    public static function register(Application $app): void
    {
        (new BulkHttpRegistrar)->registerRoutes($app);
    }

    public function submit(): mixed
    {
        return $this->run($this->submitInScope(...));
    }

    private function submitInScope(DataScope $scope): JsonResponse
    {
        $request = $this->request();
        $key = $request->headers->get('Idempotency-Key');

        if ($key === null || $key === '') {
            return response()->json(['code' => 'idempotency_required'], 400);
        }

        $definition = $this->definition();
        $clientId = $this->clientId();

        if ($definition->actorId !== $scope->principalId || $definition->clientId !== $clientId
            || get_object_vars(resolve(DataScopeCodec::class)->decode($definition->encodedScope)) !== get_object_vars($scope)) {
            return response()->json(['code' => 'not_found'], 404);
        }

        $operation = $this->operation($definition);

        if ($operation === null) {
            return response()->json(['code' => 'not_found'], 404);
        }

        $principal = resolve(PrincipalResolver::class)->resolve($request);

        if ($principal === null) {
            return response()->json(['code' => 'authentication_required'], 401);
        }

        $metadata = InvocationMetadataFactory::fromRequest($request, $definition->major);
        $context = resolve(ContextResolver::class)->resolve($request, $principal, new InvocationMetadata(
            $metadata->requestId, $metadata->version, $operation->id, $metadata->method, $metadata->correlationId, $metadata->accept,
        ));
        $options = resolve(BulkOptions::class);
        $semantic = [
            'input_ref' => JsonDocuments::map($definition->inputRef),
            'extension_stamp' => JsonDocuments::map($definition->extensionStamp),
            'row_limit' => min($definition->rowLimit, $options->rowLimit),
            'time_limit_seconds' => min($definition->timeLimitSeconds, $options->timeLimitSeconds),
        ];
        $invocation = new OperationInvocation($operation, $context, JsonDocuments::capture($semantic), [], $key, $clientId);
        $request->attributes->set(OperationInvocation::class, $invocation);

        try {
            resolve(Factory::class)->make($semantic, $operation->inputRules)->validate();
            $result = resolve(OperationInvoker::class)->invoke($invocation);
            $id = JsonDocuments::map($result->data)['operation_id'] ?? null;

            return is_string($id)
                ? resolve(BulkOperations::class)->accepted($scope, $clientId, $id, resolve(UrlGenerator::class)->route('corex.api.bulk.show', ['id' => $id], false))
                : response()->json(['code' => 'not_found'], 404);
        } catch (ApiError $error) {
            return response()->json(['code' => $error->errorCode], $error->status);
        } catch (BudgetRateLimited $error) {
            return response()->json(['code' => 'operations.budget_rate_limited'], 429, $error->getHeaders());
        } catch (BudgetStoreUnavailable) {
            return response()->json(['code' => 'budget_unavailable'], 503);
        } catch (ValidationException) {
            return response()->json(['code' => 'invalid_input'], 422);
        } finally {
            $request->attributes->remove(OperationInvocation::class);
        }
    }

    private function operation(BulkOperationDefinition $definition): ?Operation
    {
        $operations = array_values(array_filter(
            resolve(OperationRegistry::class)->all($definition->major),
            static fn (Operation $operation): bool => $operation->id === $definition->operationId,
        ));
        $operation = count($operations) === 1 ? $operations[0] : null;

        return $operation !== null && hash_equals($operation->contractHash, $definition->contractHash)
            && $operation->mode === OperationMode::BulkCommand && $operation->executionSupported
            && $operation->handler === SubmitBulkOperation::class
            && $operation->idempotency === IdempotencyRequirement::Required && $operation->effectMode === 'tenant_transactional'
            ? $operation
            : null;
    }

    public function show(string $id): mixed
    {
        return $this->run(fn (DataScope $scope): mixed => resolve(BulkOperations::class)->show(scope: $scope, clientId: $this->clientId(), id: $id));
    }

    public function cancel(string $id): mixed
    {
        return $this->run(fn (DataScope $scope): mixed => resolve(BulkOperations::class)->cancel(scope: $scope, clientId: $this->clientId(), id: $id));
    }

    public function result(string $id): mixed
    {
        return $this->run(fn (DataScope $scope): mixed => resolve(BulkOperations::class)->result(scope: $scope, clientId: $this->clientId(), id: $id));
    }

    public function failures(string $id): mixed
    {
        $request = $this->request();
        $limit = $request->integer('limit', BulkFailureLimit::DEFAULT);
        $after = $request->query('after');

        if ($after !== null && ! is_string($after)) {
            throw new InvalidArgumentException('The bulk failures cursor must be a string.');
        }

        return $this->run(fn (DataScope $scope): mixed => resolve(BulkOperations::class)->failurePage(
            scope: $scope,
            clientId: $this->clientId(),
            id: $id,
            afterOrdinal: $after,
            limit: $limit,
        ));
    }

    /** @param Closure(DataScope): mixed $action */
    private function run(Closure $action): mixed
    {
        $scope = resolve(DataScopeFactory::class)->forCurrentPrincipal(resolve(TenantContextResolver::class)->current());

        return resolve(WorkScopeRunner::class)->run($scope, static fn (): mixed => $action($scope));
    }

    private function clientId(): string
    {
        $clientId = $this->request()->attributes->get('corex.client_id');

        if (! is_string($clientId) || $clientId === '') {
            throw new InvalidArgumentException('Trusted bulk client is not bound.');
        }

        return $clientId;
    }

    private function request(): Request
    {
        return resolve(Request::class);
    }

    private function definition(): BulkOperationDefinition
    {
        $definition = $this->request()->attributes->get('corex.bulk_definition');

        if (! $definition instanceof BulkOperationDefinition) {
            throw new InvalidArgumentException('Trusted bulk definition is not bound.');
        }

        return $definition;
    }
}
