# Improvement proposals

Open proposals found while polishing the repository. Each item states the
problem, the proposal, why it is worth doing and a priority:
**P1** next, **P2** soon, **P3** when convenient.

## 1. Snapshots that pin framework internals (P1)

**Problem.** The Golden surface snapshot records trait methods that Laravel
adds to internal jobs, and the unused-public ledger stores SHA-256 hashes of
whole vendor files (`HasAttributes.php`, `Model.php`). Every Laravel patch
release turns CI red without any CoreX change; updating to 13.44 needed a
rebaseline and 47 refreshed hashes.

**Proposal.** Snapshot only members declared in CoreX sources, and anchor
vendor evidence on the installed package version from `composer.lock`
instead of file hashes.

**Rationale.** Security updates of the framework should be a lockfile change,
not a snapshot review.

## 2. English-only documentation and messages (P1)

**Problem.** The normative layer in `docs/architecture/` (constitution,
policies, package map), the root and several package CHANGELOGs, `dev-doctrine.md`
and many exception messages in `src/` (for example the deny-all authorizers in
Tasks) are in Russian. The generator of `docs/architecture/configuration.md`
also emits Russian text.

**Proposal.** Translate the architecture layer and CHANGELOGs, switch the
configuration-reference template to English, and move exception messages to
English in one pass per package.

**Rationale.** The packages are meant for external consumers; mixed languages
make the public surface and the logs harder to use.

## 3. Planning identifiers inside tooling (P2)

**Problem.** Gate scripts and data files carry planning identifiers: phase
maps in `scripts/package-docs-gate.php`, decision references in
`scripts/coverage-min-floor.txt`, plan anchors in the architecture README.
They mean nothing without the private plans.

**Proposal.** Replace them with rule names that describe the check, and drop
the phase bookkeeping from the gates.

**Rationale.** Tooling should explain itself from the repository alone.

## 4. Publish the ecosystem packages to a Composer registry (P1, owner decision)

**Problem.** Packman, Tenant, AzGuard and Vaulter are inline `type: package`
repositories pinned to Git commits. Standalone package installs need the
`composer-with-snapshots.py` wrapper, and a squashed upstream history makes the
pins unreachable (this happened to AzGuard and Tenant).

**Proposal.** Publish tagged releases to a private Packagist or Satis and
depend on version constraints.

**Rationale.** Removes the wrapper, makes installs reproducible and lets
Composer audit the ecosystem packages.

## 5. A shared test kit instead of cross-package test coupling (P2)

**Problem.** Package suites load shared fixtures from the monorepo's
`tests/Support` through a custom autoloader; core tests use the Tenancy test
case and the Meilisearch adapter tests use the root test case.

**Proposal.** Extract the fixtures and base test cases into a dev-only
`axiomasoft/corex-testkit` package that packages require in `require-dev`.

**Rationale.** Each package becomes testable from its own manifest, and the
dependency analyser no longer needs ignore rules for the shared fixtures.

## 6. Retire the frozen-baseline spike tooling (P2)

**Problem.** `scripts/quality/infection-spike.py` compares `ci.yml` with a
fixed historic commit and fails on any CI change. It is not wired into CI.

**Proposal.** Delete the spike (its receipts are history) or rebase it on the
mutation tooling that CI does run.

**Rationale.** Dead verification code misleads reviewers.

## 7. Faster CI lanes (P2)

**Problem.** Every lane runs a full root `composer install`, and the package
lanes add a standalone install. The root suite runs in one process per PHP
version.

**Proposal.** Build `vendor/` once per lockfile in a setup job and restore it
in the lanes; shard the root suite across two runners with Pest's
`--shard`.

**Rationale.** Cuts the wall time of the slowest lanes and the runner minutes.

## 8. Local PostgreSQL environment matches CI (P3)

**Problem.** `docker-compose.yml` starts PostgreSQL 16 only; CI runs 16 and 18
with Meilisearch and creates five extra databases for the PG lane.

**Proposal.** Move the compose file to PostgreSQL 18, add Meilisearch, and an
init script that creates the lane databases.

**Rationale.** `composer test:pg` should pass locally with one command.

## 9. Coverage floor in one place (P3)

**Problem.** `scripts/coverage-min-floor.txt` documents the floor, but
`composer test:coverage` hard-codes `--min=80`.

**Proposal.** Read the floor from the file in `scripts/run-root-coverage.sh`,
or delete the file.

**Rationale.** One source of truth for a gate threshold.

## 10. Identifier strategy in FlexCRM (P2, owner decision)

**Problem.** The kernel and modules use UUIDv7; some FlexCRM models still use
ULIDs, and the `flex/crm` description advertises ULIDs.

**Proposal.** Decide on one identifier type for products and migrate the
remaining models before the first release.

**Rationale.** Mixed identifier types complicate references, search and
imports.

## 11. Require the CI gate on main (P1, owner action)

**Problem.** The repository has no ruleset that requires a status check
(branch protection itself was not readable with the review token).

**Proposal.** Require the `CI gate` check on `main`.

**Rationale.** It is the single aggregated verdict; skipped lanes are already
handled.

## 12. Mutation testing cadence (P3)

**Problem.** Mutation jobs were disabled with `if: false`; they now run only on
manual dispatch.

**Proposal.** Run them weekly on a schedule and publish the MSI per package.

**Rationale.** Mutation scores decay silently when nobody runs them.

## 13. Tooling quirks worth reporting upstream (P3)

- The dependency analyser resolves the `event()` helper case-insensitively to
  an imported `Event` facade and reports a false unknown function.
- The vendored Laravel Rector rule `ServerVariableToRequestFacadeRector`
  rewrites `$_SERVER['argv']` in CLI code, which is process state, not request
  input; `scripts/` is preserved in `rector.php` for that reason.

## 14. One context check per CRM save (P2)

**Problem.** A CRM action resolves `ContextGuard::current()` before its
transaction, again with `lock: true` inside it, and the `RecordRules` saving
observer resolves it a third time for the same record. Each call re-reads the
tenant, workspace and actor rows, so one save costs 8 or more queries before
the insert.

**Proposal.** Memoise the locked context per connection and transaction
level for the lifetime of that transaction, and let `RecordRules` reuse it.
The unlocked pre-check stays, because it rejects early without opening a
transaction.

**Rationale.** The repeated locked reads protect against a context that
changes mid-request; a transaction-scoped memo keeps that guarantee, since the
first locked read already holds the row locks. It needs a careful review of
Octane worker reuse, so it is not a drive-by change.

## 15. Table probes in access checks (P3)

**Problem.** `ScopePredicate` asks the schema builder whether `users` and
`sys_departments` exist on every dept/dept_tree check, so 2 of the 3
statements per record check are catalogue queries. The probes exist for the
boxed profile, which has no such tables.

**Proposal.** Decide the profile once at boot (or per tenant connection) and
inject it, instead of probing per check.

**Rationale.** It halves the remaining queries per record check. A process
cache keyed only by connection name is unsafe under Octane when a module
migration creates the tables mid-process, so the decision belongs to the
lifecycle that creates them.

## 16. Retention for `ntf_notifications` (P2, owner decision)

**Problem.** Delivered and read notifications are never pruned. The outbox
and pub/sub tables have batched prune commands; notifications do not.

**Proposal.** A `notifications:prune` command with a configurable retention
(for example 90 days after read), using the same bounded batches as
`outbox:prune`, scheduled by the host.

**Rationale.** The table grows with every event fan-out. The retention period
is a product decision.

## 17. Unused import/export codecs (P3)

**Problem.** `CsvCodec` and `NdjsonCodec` in `import-export` are only used by
their own tests. `CsvCodec` scans the input character by character in PHP
(about 870 ms per 10 MB).

**Proposal.** Either wire them into the transfer runner, with a streaming
decoder built on `fgetcsv` over a `php://temp` stream, or remove them.

**Rationale.** Internal code without a caller still costs reviews and
security audits.

## 18. Standalone suites that need a sibling package (P3)

**Problem.** The media tests only run from their own package install, and an
isolated tenancy run shows 8 `ClaimLifecycleConformanceTest` failures that
the cohort run hides. The panels suite needed `corex-custom-fields` as a dev
requirement before it could run standalone.

**Proposal.** Run every package suite both standalone and from the root
vendor in CI, and fix whichever mode fails.

**Rationale.** A package that only passes inside the monorepo is not
independently installable.

## 19. More upstream notes (P3)

- Packman's own validators use `'/^...$/'` without the `D` modifier, so they
  accept a trailing newline. CoreX fixed its own patterns and guards them in
  `tests/Arch/RegexAnchorTest.php`; the same fix belongs in Packman.
- The vendored Rector rule `SleepFuncToSleepStaticCallRector` rewrites
  `usleep()` in scripts that run without the Composer autoloader;
  `rector.php` skips the one affected test helper.
