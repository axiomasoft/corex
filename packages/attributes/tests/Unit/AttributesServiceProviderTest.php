<?php

declare(strict_types=1);

namespace CoreX\Attributes\Tests\Unit;

use CoreX\Attributes\AttributesServiceProvider;
use CoreX\Attributes\Contracts\AttributeFilter;
use CoreX\Attributes\Contracts\AttributeStore;
use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Contracts\SchemaOperations;
use CoreX\Attributes\Internal\UnavailableAttributes;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\CoreServiceProvider;
use CoreX\Support\Data\DenyDataScopeAuthorizer;
use Orchestra\Testbench\TestCase;
use Override;

final class AttributesServiceProviderTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(DataScopeAuthorizer::class, DenyDataScopeAuthorizer::class);
        $this->app->forgetScopedInstances();
    }

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, AttributesServiceProvider::class];
    }

    public function test_it_fails_closed_without_the_existing_entity_registry(): void
    {
        expect($this->app->make(FieldCatalogue::class))->toBeInstanceOf(UnavailableAttributes::class)
            ->and($this->app->make(AttributeStore::class))->toBeInstanceOf(UnavailableAttributes::class)
            ->and($this->app->make(AttributeFilter::class))->not->toBeInstanceOf(UnavailableAttributes::class)
            ->and($this->app->make(SchemaOperations::class))->toBeInstanceOf(UnavailableAttributes::class);
    }
}
