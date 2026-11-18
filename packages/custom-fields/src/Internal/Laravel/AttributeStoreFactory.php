<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Laravel;

use CoreX\CustomFields\Contracts\AttributeStore;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Data\AttributeStoragePolicy;
use CoreX\CustomFields\Data\AttributeTables;
use CoreX\CustomFields\Internal\Integration\AttributeMessageTranslator;
use CoreX\CustomFields\Internal\Storage\ConfiguredAttributeStoreResolver;
use CoreX\CustomFields\Internal\Storage\ResolvingAttributeStore;
use CoreX\CustomFields\Internal\UnavailableAttributes;
use CoreX\CustomFields\Internal\Values\EavAttributeStore;
use CoreX\CustomFields\Internal\Values\JsonbAttributeStore;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Messaging\Contracts\Outbox;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

/** @internal Composition of the AttributeStore binding. */
#[Internal]
final class AttributeStoreFactory
{
    public function make(Application $app): AttributeStore
    {
        $composition = $app->make(AttributeComposition::class);
        $catalogue = $app->make(FieldCatalogue::class);
        $tables = $app->make(AttributeTables::class);

        if (! $app->bound('db')) {
            return $app->make(UnavailableAttributes::class);
        }

        $connection = $app->make(DatabaseManager::class)->connection();
        $authorizer = $app->make(DataScopeAuthorizer::class);
        $outbox = $app->make(Outbox::class);
        $messageTranslator = $app->make(AttributeMessageTranslator::class);
        $clock = $app->make(ClockInterface::class);
        $jsonb = ($composition->drivers['jsonb'] ?? false)
            ? new JsonbAttributeStore(
                connection: $connection,
                tables: $tables,
                catalogue: $catalogue,
                authorizer: $authorizer,
                outbox: $outbox,
                messageTranslator: $messageTranslator,
                clock: $clock,
            )
            : null;
        $eav = ($composition->drivers['eav'] ?? false)
            ? new EavAttributeStore(
                connection: $connection,
                tables: $tables,
                catalogue: $catalogue,
                authorizer: $authorizer,
                outbox: $outbox,
                messageTranslator: $messageTranslator,
                clock: $clock,
            )
            : null;

        if ($jsonb === null && $eav === null) {
            return $app->make(UnavailableAttributes::class);
        }

        return new ResolvingAttributeStore(new ConfiguredAttributeStoreResolver(
            policy: new AttributeStoragePolicy(default: $composition->storageDriver, entities: $composition->storageByEntity),
            jsonb: $jsonb,
            eav: $eav,
        ));
    }
}
