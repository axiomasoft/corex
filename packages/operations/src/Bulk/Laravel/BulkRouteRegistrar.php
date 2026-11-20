<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use Closure;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Data\Data\DataScope;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Internal\BulkFailureLimit;
use CoreX\Operations\Bulk\Internal\BulkOperations;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
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
        return $this->run(fn (DataScope $scope): mixed => resolve(BulkOperations::class)->submit(
            scope: $scope,
            clientId: $this->clientId(),
            idempotencyKey: $this->request()->header('Idempotency-Key'),
            definition: $this->definition(),
        ));
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
