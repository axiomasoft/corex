# axiomasoft/corex-search-custom-fields

Projects effective Custom Fields into CoreX Search and forwards committed owner facts to Search. Fields are addressed by stable field ID. Run ordinary field reads and updates through Custom Fields.

## Installation

```bash
composer require axiomasoft/corex-search-custom-fields
```

Laravel discovers the bridge provider. Apply the Core, Custom Fields and Search schema sets for the host.

## Configuration

Set `COREX_SEARCH_CUSTOM_FIELDS_ENABLED=true` before boot or set `enabled` to `true` in `config/corex-search-custom-fields.php`. The default is `false`. The bridge installs no tables.

## Subscriber contract

Route committed `fields.values.changed.v2` and `fields.schema.changed.v2` deliveries to the logical consumer `search.custom-fields`. Resolve the subscriber in the host broker worker and pass the restricted transport reference.

```php
use CoreX\SearchCustomFields\Invalidation\Contracts\FieldSearchSubscriber;

$receipt = app(FieldSearchSubscriber::class)->consume($restrictedRef);
```

The broker receiver commits the inbox receipt and the Search effect on one verified writer. A value fact enqueues a reread of its original record. A schema fact records a rebuild request for the current mapping. In its verified Search worker scope, the host can poll `SearchMappingInvalidations::pending` and start and publish a compatible generation through the existing typed ports.

The bridge projects exact scalar values using the effective schema and the Fields codec. Equality and membership depend on the field's operator set and the active Search driver. Decimal values remain exact strings. Numeric decimal sorting and compound value types require a projection with matching transport semantics.

## Failure semantics

Search closes an incompatible generation when the owner switches its effective revision. Queries can resume after publication of a matching generation. Stale cursors raise a typed Search error. Search checks each requested field use independently, and Custom Fields applies record and field read authorization when loading values. A query that uses a Custom Field in a predicate, sort or facet requires an owner read grant covering the affected records, so Search can verify the current values. Unsupported filter values and unavailable capabilities raise typed Search errors before the backend query.

The enabled bridge uses the owner’s effective schema locks. PostgreSQL queries still return authorized hits, and requested exact counts and facets are reported as unavailable.

The disabled bridge registers neither the projection nor the subscriber.

## Testing

```bash
composer --working-dir=packages/search-custom-fields test
composer --working-dir=packages/search-custom-fields test:pg
```
