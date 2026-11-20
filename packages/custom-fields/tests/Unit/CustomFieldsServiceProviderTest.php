<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Tests\Unit;

use CoreX\CoreServiceProvider;
use CoreX\CustomFields\CustomFieldsServiceProvider;
use CoreX\CustomFields\Query\Contracts\FieldFilter;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Contracts\FieldSchemas;
use CoreX\CustomFields\Types\Contracts\FieldTypes;
use CoreX\CustomFields\Values\Contracts\FieldValues;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use Orchestra\Testbench\TestCase;

final class CustomFieldsServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, CustomFieldsServiceProvider::class];
    }

    public function test_standalone_composition_registers_v2_contracts(): void
    {
        foreach ([FieldFilter::class, FieldCatalogue::class, FieldSchemas::class, FieldTypes::class, FieldValues::class] as $port) {
            self::assertTrue($this->app->bound($port));
        }
        $contracts = $this->app->make(MessageContractRegistry::class);
        foreach (['fields.values.changed', 'fields.schema.requested', 'fields.schema.changed'] as $name) {
            self::assertSame(2, $contracts->get($name, 2)->version);
        }
        self::assertFalse($contracts->has('attributes.values.changed', 1));
    }
}
