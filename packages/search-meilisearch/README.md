# axiomasoft/corex-search-meilisearch

An optional candidate adapter for `axiomasoft/corex-search`. Installing the package registers its drivers. Selecting the adapter uses the host's endpoint and credentials, the Search cluster identity, and the immutable UID of the current SQL generation.

The adapter pages by opaque record position and supplies candidate identities for current owner hydration. Exact count and facets are unavailable in this profile.

## Installation

Install the optional adapter with `composer require axiomasoft/corex-search-meilisearch` alongside `axiomasoft/corex-search`. Set `corex-search-meilisearch.host` and select the `meilisearch` driver in Search. Set `corex-search.cluster` to the identifier for that deployment.

Bind `MeilisearchCredentialProvider` to supply provider-enforced credentials for the exact generation UID. Supply search-only access for candidate queries, document and index mutation access for submissions, and index, settings, and task read access for verification. Provision credentials in the host or secrets service before use.

## Extension contracts

Use `CoreX\Search\Query\Contracts\Search` for queries and the Search rebuild API to publish a generation. The application supplies the current record source, projection fields, and authorization policy.

## Failure semantics

The durable Search lane verifies the exact Meilisearch task receipt before advancing a generation. An ambiguous submission quarantines its UID, and a rebuild allocates a fresh UID. The active pointer changes in SQL after the destination is confirmed.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.
