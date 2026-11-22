# axiomasoft/corex-tenancy

CoreX account/workspace metadata, membership, domain identification and account lifecycle
orchestration. Isolation is executed by **axiomasoft/tenant**. Physical resources belong to
the host-selected owner, normally `axiomasoft/tenant-postgres`; CoreX does not switch
connections, clone databases, run `pg_dump` or execute database deletion.

## Installation

Configure the application's [ecosystem dependency channel](../../docs/architecture/ecosystem-dependencies.md),
then require `axiomasoft/corex-tenancy:^2.0`. Choose the matching
`axiomasoft/tenant-laravel` and physical-owner adapter in the host; these are optional
host dependencies, not a bundled deployment profile. CoreX's provider is discovered
by Laravel. Publish domain options with `php artisan vendor:publish --tag=corex-tenancy-config`.

Bind the host contracts below before running account operations. Install central
metadata with `php artisan packman:schema:migrate --scope=central --database=<central>`
and provision each account's tenant schema through the chosen resource owner.
Use `--force` for unattended production migration. Installing the package alone
does not create physical resources or choose trusted tenant mappings.

## Required host composition

Bind these contracts before invoking tenant operations:

- `Tenant\Contracts\Runtime\TenantRuntime`: the single executor shared by the application.
  `axiomasoft/tenant-laravel` composes the executor and guarded Laravel facilities.
- `CoreX\Tenancy\Runtime\Contracts\AccountIsolationMapper`: translates a trusted account
  and workspace into a Tenant `Selection`, and projects its verified `ContextState`.
- `CoreX\Tenancy\Provisioning\Contracts\AccountResources`: physical provision, export,
  archive verification, deletion and unclaimed-pool cleanup.
- `CoreX\Tenancy\Provisioning\Contracts\TemplateIntegrityChecks` and
  `CoreX\Tenancy\Contracts\TemplateIntegrityVerifier`: the owner's template attestations.

There is no permissive default or legacy runtime. Missing bindings fail when required and
are reported by CoreX configuration diagnostics. The host chooses its Tenant profile,
resource mappings and facilities; request input is never trusted binding metadata.
A production physical profile must verify resource reference, incarnation and route
revision with its physical owner. The synthetic no-I/O profile used in unit tests is
not a PostgreSQL deployment configuration.

`TenantRuntimeManager` implements CoreX's `TenancyManager`. `runFor()` delegates to
`TenantRuntime::run()`, including nested restoration, leaked-frame detection and poison
semantics. Manual `initialize()`/`end()` own only frames they opened; they preserve a
surrounding host middleware frame. A recoverable restore precondition failure retains
the frame so its resource can be closed and restoration retried. Scoped Laravel services
are forgotten on entry, workspace changes and restoration.

## Physical ownership and receipts

CoreX stores account lifecycle and export metadata. `AccountResources` performs the
physical effect and returns only after the owner's committed receipt. Pending or unknown
outcomes are not success; the host retains operation keys and reconciles them at the owner.
`AccountArchive.reference` is an opaque owner reference; the existing metadata column
`storage_path` stores it and must not be interpreted as a local path. Formats are recorded
only when declared by the owner. A prior boolean integrity check does not authorize a
later deletion: the owner revalidates the current incarnation, archive and admission as
part of its deletion operation.

The actual Tenant PostgreSQL API is `PostgresLifecycle::claim()`, `requestExport()`,
`queryExport()`, `requestDelete()`, `queryOperation()` and `reconcile()`. Requests require
`ProvisionIntent`/`ExportIntent`/`DeleteIntent`, an `OperationKey` and fresh `Admission`.
CoreX cannot infer those from a database name: the host adapter maps its approved template,
manifest hash, resource binding, retention policy and durable operation identity.
`ResourceBindings::resolve()` supplies verified process-local physical access.

CoreX's pending-account pool retains signup metadata and claims. Cleanup commits
`pool_cleanup` before requesting owner deletion and retains the marker on failure; the
owner independently refuses deletion of a claimed resource. Account activation and
terminal account deletion are published only after the corresponding owner effect succeeds.

## Host identification and support

Publish `corex-tenancy-config`; account/domain identification, OIDC, membership and
impersonation remain CoreX policies. Support impersonation is opt-in through
`corex-tenancy.oidc.impersonation.enabled`. The `standard` and `regulated` profiles enforce
their audit/approval requirements; production `dev` requires the existing explicit
`corex.acknowledged_dangerous_modes` acknowledgement. Physical isolation remains Tenant's
responsibility in every profile.

`corex-tenancy.wakeup.driver=root_registry` supplies advisory account wakeup hints.
Install `corex-scheduler` and implement `CoreX\Context\Contracts\NamedWorkPrincipal`
with an explicit account-scoped grant for `corex.scheduler-sweep`. The root sweep and
reconciliation commands use that grant to enter the account. Scheduler restores each
stored request’s workspace and principal separately. Native `bootstrappers` configuration
has been removed. Configure connection, cache and session facilities in the host Tenant
composition.

`TenantContextInitialized` and `TenantContextEnded` notify explicit CoreX
`initialize`/`setWorkspace`/`end` calls only. Observe protected `runFor` executions
through Tenant's observation facilities.

### Identity synchronization

Identity fan-out and reconciliation run under the account-wide named service grant for corex.identity-sync. Configure NamedWorkPrincipal together with WorkPrincipalAuthority to verify this service in the target account. Each queued synchronization records its producer scope and reloads the central membership before changing the corresponding tenant user.
