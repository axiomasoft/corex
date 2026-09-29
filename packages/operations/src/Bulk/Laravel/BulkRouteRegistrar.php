<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Data\DataScope;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Internal\BulkOperations;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/** @internal Literal bulk routes registered before the version wildcard. */
final readonly class BulkRouteRegistrar
{
    public static function register(Application $app): void
    {
        $prefix = (string) $app['config']->get('corex-operations.routes.prefix', 'api');

        Route::prefix($prefix)->group(static function (): void {
            Route::post('v1/bulk-operations', [self::class, 'submit'])->name('corex.api.bulk.submit');
            Route::get('v1/bulk-operations/{id}', [self::class, 'show'])->name('corex.api.bulk.show');
            Route::post('v1/bulk-operations/{id}/cancel', [self::class, 'cancel'])->name('corex.api.bulk.cancel');
            Route::get('v1/bulk-operations/{id}/result', [self::class, 'result'])->name('corex.api.bulk.result');
            Route::get('v1/bulk-operations/{id}/failures', [self::class, 'failures'])->name('corex.api.bulk.failures');
        });
    }

    public function submit(): mixed
    {
        return $this->operations()->submit(
            $this->scope(),
            $this->clientId(),
            request()->header('Idempotency-Key'),
            $this->definition(),
        );
    }

    public function show(string $id): mixed
    {
        return $this->operations()->show($this->scope(), $this->clientId(), $id);
    }

    public function cancel(string $id): mixed
    {
        return $this->operations()->cancel($this->scope(), $this->clientId(), $id);
    }

    public function result(string $id): mixed
    {
        return $this->operations()->result($this->scope(), $this->clientId(), $id);
    }

    public function failures(string $id): mixed
    {
        $limit = request()->integer('limit', BulkFailureLimit::DEFAULT);

        return $this->operations()->failurePage(
            $this->scope(),
            $this->clientId(),
            $id,
            request()->query('after'),
            $limit,
        );
    }

    private function operations(): BulkOperations
    {
        return app(BulkOperations::class);
    }

    private function scope(): DataScope
    {
        return app(DataScope::class);
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

/** @internal Default failure page size stays inside the hard cap. */
final class BulkFailureLimit
{
    public const int DEFAULT = 50;
}
