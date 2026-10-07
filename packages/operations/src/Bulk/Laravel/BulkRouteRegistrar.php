<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Internal\BulkFailureLimit;
use CoreX\Operations\Bulk\Internal\BulkOperations;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class BulkRouteRegistrar
{
    public static function register(Application $app): void
    {
        (new BulkHttpRegistrar)->registerRoutes($app);
    }

    public function submit(): mixed
    {
        return $this->operations()->submit(
            scope: $this->scope(),
            clientId: $this->clientId(),
            idempotencyKey: request()->header('Idempotency-Key'),
            definition: $this->definition(),
        );
    }

    public function show(string $id): mixed
    {
        return $this->operations()->show(scope: $this->scope(), clientId: $this->clientId(), id: $id);
    }

    public function cancel(string $id): mixed
    {
        return $this->operations()->cancel(scope: $this->scope(), clientId: $this->clientId(), id: $id);
    }

    public function result(string $id): mixed
    {
        return $this->operations()->result(scope: $this->scope(), clientId: $this->clientId(), id: $id);
    }

    public function failures(string $id): mixed
    {
        $limit = request()->integer('limit', BulkFailureLimit::DEFAULT);

        return $this->operations()->failurePage(
            scope: $this->scope(),
            clientId: $this->clientId(),
            id: $id,
            afterOrdinal: request()->query('after'),
            limit: $limit,
        );
    }

    private function operations(): BulkOperations
    {
        return resolve(BulkOperations::class);
    }

    private function scope(): DataScope
    {
        return resolve(DataScope::class);
    }

    private function clientId(): string
    {
        $clientId = request()->attributes->get('corex.client_id');

        if (! is_string($clientId) || $clientId === '') {
            throw new InvalidArgumentException('Trusted bulk client is not bound.');
        }

        return $clientId;
    }

    private function definition(): BulkOperationDefinition
    {
        $definition = request()->attributes->get('corex.bulk_definition');

        if (! $definition instanceof BulkOperationDefinition) {
            throw new InvalidArgumentException('Trusted bulk definition is not bound.');
        }

        return $definition;
    }
}
