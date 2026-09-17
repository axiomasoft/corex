# corex/search-meilisearch

An optional candidate adapter for `corex/search`. It is disabled by default and never opens a network connection during provider registration. Applications own the endpoint, credentials, index UID, and current-policy hydration.

The adapter returns asynchronous task receipts for document submission and swaps. A `202` receipt is not completion; the durable lane in `corex/search` must reconcile the task before progressing.
