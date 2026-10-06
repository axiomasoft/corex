# axiomasoft/corex-tenancy

Laravel tenancy primitives for CoreX's physical PostgreSQL database-per-account
profile.

## Support impersonation

Enable the OIDC impersonation lane with `corex-tenancy.oidc.impersonation.enabled`.
Choose `corex-tenancy.impersonation.profile`: `standard` (default, 60-minute maximum grant),
`regulated` (30 minutes and approval by a different staff identity), or `dev` (480 minutes).
Production `dev` requires `corex.acknowledged_dangerous_modes` to contain
`corex-tenancy.impersonation.profile`. All grants are claimed once atomically and must carry a
reason. `standard` and `regulated` commit correlated central and tenant audit rows before login;
`dev` commits central audit only. Any audit failure denies access and leaves the grant consumed.
The central intent remains if the tenant mirror fails. Hosts may implement
`ImpersonationGrantStore` and `ImpersonationAuditSink`; a custom sink must preserve committed,
fail-closed writes. The current scoped `ImpersonatedModelWriteGuard` instance provides
`allowing()` for a deliberately permitted write.

## Optional wakeup root hints

`corex-tenancy.wakeup.driver` defaults to `local_sweep`. With `axiomasoft/corex-wakeup` installed, a cloud
deployment may explicitly select `root_registry`; this enables the advisory `root_wakeup_hints`
table and the `corex:tenancy:wakeup-root-sweep` command. Hints never replace a tenant-local request or
provide authority to select an account connection.

## Package engine and configuration

CoreX owns the account/domain models, physical tenancy engine and synchronous golden-template
provisioning. Account identifiers use UUIDv7; account fields are explicit typed columns.
Signup idempotency is stored in explicit `signup_*` columns, written atomically by the claim SQL.
A named all-or-nothing CHECK and partial unique index protect the binding. Cleanup commits
`pool_cleanup` before dropping a stale database and resumes interrupted cleanup from that marker.
These fields are excluded from model mass assignment. Saved/deleted model events fence the central host cache
after commit. Positive entries carry a shared generation so late lookups cannot restore revoked mappings;
account deletion cascades also invalidate them. Changes to any account/domain invalidate all warmed host
mappings, which are rebuilt on demand. Resolutions inside transactions bypass the shared cache.

Publish configuration with `php artisan vendor:publish --tag=corex-tenancy-config`. All settings,
including wakeup, use `config/corex-tenancy.php` under `corex-tenancy`. Migrate host overrides from the
previous `tenancy` key; the old key and model/provider configuration knobs have been removed.

## Template integrity

The internal verifier accepts a ready template only when it has all of the
following:

- a lowercase SHA-256 `schema_hash` recorded in `root_templates`;
- a configured Ed25519-signed manifest from a configured trusted key; and
- a matching canonical, read-only PostgreSQL catalogue hash.

`corex-tenancy.template_integrity.trusted_keys` and
`corex-tenancy.template_integrity.manifests` are deliberately empty defaults.
Existing ready rows with a NULL hash are not compatible fallback templates.
Template integrity metadata is part of the fresh baseline schema.

## Compatibility evidence

The factual compatibility passport, its local proof class, and explicit limits
are in [PACKAGE.md](PACKAGE.md). It does not extend Composer constraints or
constitute a publication promise.

## Installation

Install with `composer require axiomasoft/corex-tenancy`. Prepare PostgreSQL root and physical account databases with separate trusted connection configuration. Apply each schema only to its declared owner connection; provider discovery is not permission to migrate a customer database.

## Extension contracts

Applications supply the exported workspace resolution, domain trust/authorization, provisioning and template integrity ports for their deployment. Compose optional wakeup through its declared adapter. Account connection selection must come from authenticated trusted context, never unvalidated request fields.

## Failure semantics

An untrusted or incomplete tenant context must not select an account database. Template provisioning refuses missing/mismatched hashes, manifests and signatures. A failed provisioning claim requires reconciliation of durable state before retry; optional advisory wakeup hints never authorize a connection switch.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.

## Schema installation

Schema: `root` (Central) and `tenant` (Tenant), baseline v1, PostgreSQL.

Register the package provider, then run `php artisan corex:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `corex:schema:status`.

### Registered schema scopes

Use `corex:schema:migrate --scope=central --database=<central>` for the control plane and `corex:schema:migrate --scope=tenant --modules --database=<template>` for an account template. Migration waves run all platform Tenant sets on the account connection; enabled modules upgrade through their lifecycle.
