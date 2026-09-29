# axiomasoft/corex-media

Private, tenant-scoped media lifecycle primitives for CoreX. The package stores no public URLs and defaults to denying every media action until an application supplies a `MediaPolicy`.

`MediaLibrary` handles private upload, authorized reads, and deletion requests. `RelationLookup` records opaque consumer attachment edges without foreign keys into consumer tables. Deletion enqueues a durable purge operation; `corex:media:sweep` dispatches due purge work in an already trusted tenant job context.

When PHP's `ext-gd` is available, the package registers its bounded `thumbnail` profile for PNG and JPEG sources. Conversions are keyed by source version and profile digest; callers receive a reference only after durable publication. Without GD, no conversion profile is registered and upload/read lifecycle remains available.

Consumers must bind `MediaPolicy`, provide the existing `TenantStorage` contract, and enforce their own entity availability and authorization in the policy. Retention, legal holds, backup, and physical storage-provider guarantees remain consumer and provider responsibilities.

## Installation

Install with `composer require axiomasoft/corex-media` through your configured distribution channel. Supply the existing tenant storage binding and a `MediaPolicy`, then install the package-owned media schema on the tenant connection. Upload/read permissions remain denied until the host policy authorizes them; configure the private storage disk before accepting uploads.
