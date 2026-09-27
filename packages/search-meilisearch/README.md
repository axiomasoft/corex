# corex/search-meilisearch

An optional candidate adapter for `corex/search`. It is disabled by default and never opens a network connection during provider registration. Applications own the endpoint, credentials, index UID, and current-policy hydration.

The adapter returns asynchronous task receipts for document submission and swaps. A `202` receipt is not completion; the durable lane in `corex/search` must reconcile the task before progressing.

## Installation

Install the optional adapter with `composer require corex/search-meilisearch` alongside `corex/search`. Configure an application-owned Meilisearch endpoint, credentials and index UID before selecting it. Installing the package alone does not connect to the service.

## Extension contracts

Use the public search provider/driver boundary of `corex/search` to select the adapter and supply current-policy hydration. The application owns its index schema and entity visibility; adapter internals are not an additional public extension surface.

## Failure semantics

An asynchronous task receipt is acceptance, not completed indexing. Reconcile completion or failure before advancing the durable search lane. Missing endpoint/key/index configuration and transport failures must not be converted into an empty successful search or a completed swap.
