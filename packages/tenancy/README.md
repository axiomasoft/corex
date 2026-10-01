# axiomasoft/corex-tenancy

Laravel tenancy primitives for CoreX's physical PostgreSQL database-per-account
profile.

## Optional wakeup root hints

`corex-tenancy.wakeup.driver` defaults to `local_sweep`. With `axiomasoft/corex-wakeup` installed, a cloud
deployment may explicitly select `root_registry`; this enables the advisory `root_wakeup_hints`
table and the `corex:tenancy:wakeup-root-sweep` command. Hints never replace a tenant-local request or
provide authority to select an account connection.

## Local immutable provider proof

The unreleased `4.0.0-alpha1` tenancy candidate is tested only through the
sealed local Composer transport described in the distribution proof. It is not
a public package channel: consumers must configure an independently approved
root repository before resolving `axiomasoft/stancl-tenancy` and
`axiomasoft/stancl-jobpipeline`. This package does not declare a path, file, or
development-sibling repository and never falls back to the moving Stancl branch.

## Template integrity

The internal verifier accepts a ready template only when it has all of the
following:

- a lowercase SHA-256 `schema_hash` recorded in `root_templates`;
- a configured Ed25519-signed manifest from a configured trusted key; and
- a matching canonical, read-only PostgreSQL catalogue hash.

`tenancy.template_integrity.trusted_keys` and
`tenancy.template_integrity.manifests` are deliberately empty defaults.
Existing ready rows with a NULL hash are not compatible fallback templates.
Consumer write-path adoption is intentionally separate. Operational rollback
preserves the integrity metadata; the additive migration has no destructive
automatic down action.

## Compatibility evidence

The factual compatibility passport, its local proof class, and explicit limits
are in [PACKAGE.md](PACKAGE.md). It does not extend Composer constraints or
constitute a publication promise.

## Installation

Install with `composer require axiomasoft/corex-tenancy` after configuring the approved immutable provider channels described below. Prepare PostgreSQL root and physical account databases with separate trusted connection configuration. Apply each schema only to its declared owner connection; provider discovery is not permission to migrate a customer database.

## Extension contracts

Applications supply the exported workspace resolution, domain trust/authorization, provisioning and template integrity ports for their deployment. Compose optional wakeup through its declared adapter. Account connection selection must come from authenticated trusted context, never unvalidated request fields.

## Failure semantics

An untrusted or incomplete tenant context must not select an account database. Template provisioning refuses missing/mismatched hashes, manifests and signatures. A failed provisioning claim requires reconciliation of durable state before retry; optional advisory wakeup hints never authorize a connection switch.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.
