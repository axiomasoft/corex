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
