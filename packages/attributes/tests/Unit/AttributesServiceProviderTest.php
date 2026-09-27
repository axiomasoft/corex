<?php

declare(strict_types=1);

namespace CoreX\Attributes\Tests\Unit;

use CoreX\Attributes\AttributesServiceProvider;
use CoreX\Attributes\Contracts\AttributeFilter;
use CoreX\Attributes\Contracts\AttributeStore;
use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Contracts\SchemaOperations;
use CoreX\Attributes\Internal\Laravel\AttributeComposition;
use CoreX\Attributes\Internal\Schema\StaticFieldCatalogue;
use CoreX\Attributes\Internal\Storage\ResolvingAttributeStore;
use CoreX\Attributes\Internal\UnavailableAttributes;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\CoreServiceProvider;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Support\Data\DenyDataScopeAuthorizer;
use CoreX\Support\InvalidConfiguration;
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

    public function test_it_resolves_a_static_catalogue_without_modules(): void
    {
        expect($this->app->make(FieldCatalogue::class))->toBeInstanceOf(StaticFieldCatalogue::class)
            ->and($this->app->make(AttributeStore::class))->toBeInstanceOf(ResolvingAttributeStore::class)
            ->and($this->app->make(AttributeFilter::class))->not->toBeInstanceOf(UnavailableAttributes::class)
            ->and($this->app->make(SchemaOperations::class))->toBeInstanceOf(UnavailableAttributes::class)
            ->and($this->app->make(MessageContractRegistry::class)->has('attributes.values.changed', 1))->toBeTrue();
    }

    public function test_it_rejects_an_invalid_source_order(): void
    {
        expect(fn () => AttributeComposition::fromArray([
            'sources' => ['module', 'static'],
            'storage_driver' => 'jsonb',
            'drivers' => ['jsonb' => true],
        ]))->toThrow(InvalidConfiguration::class);
    }
}
