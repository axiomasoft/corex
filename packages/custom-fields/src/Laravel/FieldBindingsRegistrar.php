<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Laravel;

use CoreX\CustomFields\Internal\CustomFieldsOptions;
use CoreX\CustomFields\Internal\FieldMessageRegistry;
use CoreX\CustomFields\Internal\OutboxSchemaFacts;
use CoreX\CustomFields\Query\Contracts\FieldFilter;
use CoreX\CustomFields\Query\Internal\SchemaFieldFilter;
use CoreX\CustomFields\Schemas\Contracts\SchemaFacts;
use CoreX\CustomFields\Types\Contracts\FieldConversions;
use CoreX\CustomFields\Types\Contracts\FieldConverterProvider;
use CoreX\CustomFields\Types\Contracts\FieldType;
use CoreX\CustomFields\Types\Contracts\FieldTypes;
use CoreX\CustomFields\Types\Internal\RegisteredFieldTypes;
use CoreX\CustomFields\Values\Contracts\FieldValues;
use CoreX\CustomFields\Values\Internal\JsonbFieldValues;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Internal;
use Packman\Foundation\Contracts\PackageRegistrar;

#[Internal]
final class FieldBindingsRegistrar implements PackageRegistrar
{
    public function register(Application $app): void
    {
        $app->singleton(CustomFieldsOptions::class, static fn (Application $app): CustomFieldsOptions => CustomFieldsOptions::fromArray((array) $app->make(Repository::class)->get('corex-custom-fields', [])));
        $app->singleton(RegisteredFieldTypes::class, static fn (Application $app): RegisteredFieldTypes => new RegisteredFieldTypes(extensions: $app->tagged(FieldType::class), providers: $app->tagged(FieldConverterProvider::class)));
        $app->alias(RegisteredFieldTypes::class, FieldTypes::class);
        $app->alias(RegisteredFieldTypes::class, FieldConversions::class);
        $app->scoped(FieldValues::class, JsonbFieldValues::class);
        $app->scoped(FieldFilter::class, SchemaFieldFilter::class);
        $app->scoped(SchemaFacts::class, OutboxSchemaFacts::class);
        $app->extend(MessageContractRegistry::class, static fn (MessageContractRegistry $registry): FieldMessageRegistry => new FieldMessageRegistry($registry));
    }

    public function boot(Application $app): void
    {
        $app->make(CustomFieldsOptions::class);
        $app->make(FieldTypes::class);
    }
}
