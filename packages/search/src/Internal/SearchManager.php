<?php

declare(strict_types=1);

namespace CoreX\Search\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Data\DataScope;
use CoreX\Search\Contracts\SearchDriverRegistry;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Contracts\SearchSchema;
use CoreX\Search\Exceptions\InvalidSearchConfiguration;
use CoreX\Search\Internal\Postgres\PostgresSearchProvider;
use CoreX\Search\Internal\Query\SearchCursorCodec;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final class SearchManager implements SearchDriverRegistry
{
    /** @var array<string, Closure(Application): SearchProvider> */
    private array $factories = [];

    public function register(string $name, Closure $factory): void
    {
        $this->factories[$name] = $factory;
    }

    public function driver(Application $app, SearchOptions $options): SearchProvider
    {
        $driver = $options->driver;

        if (isset($this->factories[$driver])) {
            $this->currentScope($app);

            return ($this->factories[$driver])($app);
        }

        return match ($driver) {
            'null' => new UnavailableSearchProvider,
            'postgres' => $this->createPostgresDriver($app, $options),
            default => throw new InvalidArgumentException("Driver [{$driver}] not supported."),
        };
    }

    private function createPostgresDriver(Application $app, SearchOptions $options): SearchProvider
    {
        $secret = $options->cursorSecret?->reveal();

        if (! is_string($secret) || strlen($secret) < 32) {
            throw InvalidSearchConfiguration::weakCursorSecret();
        }

        if (! $app->bound(SearchSchema::class)) {
            throw InvalidSearchConfiguration::missingSchemaBinding();
        }

        if (! $app->bound('db')) {
            throw InvalidSearchConfiguration::missingDatabase();
        }

        $scope = $this->currentScope($app);
        $current = $app->make(CurrentWorkScope::class);
        $connection = $app->make(DatabaseManager::class)->connection();
        $current->assert($scope, $connection);

        return new PostgresSearchProvider(
            connection: $connection,
            current: $current,
            schemas: $app->make(SearchSchema::class),
            cursors: new SearchCursorCodec(secret: $secret),
            configuration: $options->textSearchConfig,
        );
    }

    private function currentScope(Application $app): DataScope
    {
        $context = $app->make(TenantContextResolver::class)->current();
        $scope = $app->make(DataScopeFactory::class)->forCurrentPrincipal($context);
        $app->make(CurrentWorkScope::class)->assert($scope);

        return $scope;
    }
}
