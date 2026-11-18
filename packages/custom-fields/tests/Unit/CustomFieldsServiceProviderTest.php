<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Tests\Unit;

use CoreX\CoreServiceProvider;
use CoreX\CustomFields\Contracts\AttributeFilter;
use CoreX\CustomFields\Contracts\AttributeStore;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Contracts\SchemaOperations;
use CoreX\CustomFields\CustomFieldsServiceProvider;
use CoreX\CustomFields\Internal\Laravel\AttributeComposition;
use CoreX\CustomFields\Internal\Schema\QueuedSchemaOperations;
use CoreX\CustomFields\Internal\Schema\StaticFieldCatalogue;
use CoreX\CustomFields\Internal\Storage\ResolvingAttributeStore;
use CoreX\CustomFields\Internal\UnavailableAttributes;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Internal\DenyDataScopeAuthorizer;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use Orchestra\Testbench\TestCase;
use Override;
use Packman\Config\Exceptions\InvalidConfiguration;

final class CustomFieldsServiceProviderTest extends TestCase
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
        return [CoreServiceProvider::class, CustomFieldsServiceProvider::class];
    }

    public function test_it_resolves_a_static_catalogue_with_the_kernel_module_registry(): void
    {
        expect($this->app->make(FieldCatalogue::class))->toBeInstanceOf(StaticFieldCatalogue::class)
            ->and($this->app->make(AttributeStore::class))->toBeInstanceOf(ResolvingAttributeStore::class)
            ->and($this->app->make(AttributeFilter::class))->not->toBeInstanceOf(UnavailableAttributes::class)
            ->and($this->app->make(SchemaOperations::class))->toBeInstanceOf(QueuedSchemaOperations::class)
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
