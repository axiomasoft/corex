# Maintenance roadmap

Items refer to the repository state. Release publication and GitHub rules require access to the external registry or repository settings.

## Runtime and contracts

| Item | State | Evidence or next action |
|---|---|---|
| 1. Framework-independent snapshots | Implemented | Golden derives owned members and direct composition from source and records the runtime types. Vendor entrypoints are checked as PHP tokens. The lockfile identifies the reviewed dependency. |
| 6. Retire the Infection spike | Implemented | The fixed-baseline experiment has been retired. The supported runner, inventory and scoring thresholds are documented in the [mutation guide](../../scripts/quality/README.md). |
| 9. Root coverage | Implemented | `scripts/coverage-min-floor.txt` supplies the minimum for merged coverage. The anti-weakening check compares event revisions. The scope of this percentage is the source list in `phpunit.xml`. Package gates enforce their own thresholds. |
| 10. FlexCRM identifier generation | Implemented | FlexCRM models generate UUIDv7 and use UUID columns. Operations can describe other host identifiers. Intl retains provider-supplied rate identifiers. |
| 14. Current CRM admission | Implemented | Each guarded write reads the current user, workspace and membership in a joined query and locks them when required. A host may change those rows earlier in the same transaction. |
| 15. Access scope checks | Implemented | The record check uses a join for department membership and compares properly qualified columns for a department subtree. PostgreSQL probes required tables together on the current connection and schema. Reconnect and prefix tests cover the supported configurations. |
| 16. Notification retention | Implemented | `corex:notifications:prune` compacts expired read items in the verified tenant and workspace. The archive retains the original reference, producer identity and provider evidence for late retries. It preserves unread items, entries in a digest and deliveries awaiting acknowledgement. The host schedules the command. |
| 17. Unused transfer codecs | Implemented | Host `TransferSource` implementations own input decoding. CoreX keeps its canonical export codec. An input adapter can be added when a real source needs it. |
| 20. Guard tests | Covered scenarios | Access, CRM, tenancy, messaging, notifications, media and saved-view suites exercise authorization, scope, retry and cleanup guards. Keep the individual negative controls with their associated tests. The mutation inventory records files and thresholds. Full score receipts are produced by a dedicated run. |

The mutation review identified redundant checks for cursor decoding and container resolution, checks overlapping with model scopes, and input type checks at database and driver boundaries. Keep those guards where independent callers rely on the boundary. The media suite exercises cleanup admission under lock and races two upload requests on real PostgreSQL. The notification suite exercises replay after archival, concurrent retention, digest membership and later provider confirmations.

## Documentation and local development

| Item | State | Evidence or next action |
|---|---|---|
| 2. English documentation | Implemented | The [documentation index](../index.md), architecture policies, configuration generator, package guides and changelogs are in English. |
| 3. Private planning references | Compatibility work | Use public rule names in source and diagnostics. `corex-package-docs-gate/2` identifies the package responsible for each finding. Preserve previously issued receipt formats for readers that verify historical evidence. |
| 5. Shared test kit | Deferred until distribution | Package suites load repository fixtures through `tests/Support`. A dev-only `axiomasoft/corex-testkit` is justified when independently installed packages need the shared base cases. |
| 8. Local database services | Implemented | Compose provides PostgreSQL 18 and Meilisearch, initializes test databases and waits for service readiness. Project-scoped names allow parallel checkouts with different host ports. CI also exercises PostgreSQL 16. [Testing and CI](../testing-ci.md) |
| 18. Standalone and monorepo suites | Open | Keep the package suite in its independent install lane and the root integration suite for cross-package scenarios. Run an additional install mode on affected packages when the change concerns autoloading, manifests or shared fixtures. |

## Release engineering and external actions

| Item | State | Next action |
|---|---|---|
| 4. Composer registry | Owner and distribution qualification | Choose the registry, publish Packman, AzGuard, Tenant and Vaulter dependencies, then verify installation of the CoreX split archives from version constraints. Promote tags and remove inline package metadata only after a clean consumer install succeeds. |
| 7. CI duration | Deferred pending measurement | Measure cold install time and the slowest lanes before sharing a vendor artifact or sharding root tests. Changes are currently filtered by path and independent jobs run after Quality. |
| 11. Mandatory full CI check | GitHub owner action | Require `CI gate` on `main`. `Selected CI lanes` is a separate status for targeted manual reruns. Only repository settings make a successful full run a merge prerequisite. |
| 12. Mutation cadence | Owner budget decision | Normal code changes run the inventory and parser controls. Dispatch the full mutation fragments and registry when needed. Enable a weekly default-branch run when runner capacity is approved. |
| 13. Upstream analyzer reports | Owner action | The dependency analyser can confuse the `event()` helper with an imported Event facade. The Laravel Rector server-variable rule can treat process arguments as request state. Report minimal cases upstream. |
| 19. Upstream validation and timer rules | Owner action | Preserve strict end-of-input checks in CoreX. Report corresponding Packman validator patterns and the Laravel Rector substitution of framework sleep calls in standalone processes. |
