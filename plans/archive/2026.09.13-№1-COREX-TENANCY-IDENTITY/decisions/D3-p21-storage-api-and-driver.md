---
type: owner-decision
id: D3
date: 2026-09-13
status: accepted
item: P2.1
items: [P2.1]
supersedes: []
superseded_by: null
---
# D3 — P2.1 portable storage API and local-driver qualification

**Date:** 2026-09-13
**Status:** accepted
**Actor:** CoreX owner, delegated design selection; recorded by Codex `gpt-5.6-terra/high`
**Evidence:** Owner instruction in this thread; P1.2 dossier SHA-256 `1103947d333d402beac216b29a1a24f4e6a56f8f5cba2a7304e9f218b5066808`; D2; CoreX `composer.json` and current source pins.
**Supersedes:** —
**Superseded By:** —

## Решение

For P2.1, accept the exact `CoreX\Contracts\TenantStorage` API proposed in P1.2 §2:

```php
public function disk(): \Illuminate\Contracts\Filesystem\Filesystem;
public function ephemeralDisk(): \Illuminate\Contracts\Filesystem\Filesystem;
public function capabilities(): \CoreX\Storage\StorageCapabilities;
public function usageBytes(): int;
public function exportArchive(): \CoreX\Storage\ExportArchiveRef;
```

Also accept the exact `final readonly` DTO shapes of `StorageCapabilities(bool $usage, bool $export)` and `ExportArchiveRef(string $storagePath, int $sizeBytes, DateTimeImmutable $createdAt)`, and the two typed failures `StorageCapabilityUnavailable` and `StorageOperationFailed` described in the same section. These additions are public only in the next CoreX **0.2.0** release; no existing contract, namespace, or signature moves.

P2.1 uses a local-only, boxed adapter. It derives its account solely from `TenantContextResolver`, captures the account/root when a handle is obtained, and rechecks the current context for every operation. Public callers cannot pass an account, workspace, disk, bucket, root, prefix, URL, or untrusted path root. The boxed constant account remains `00000000-0000-7000-8000-000000000000`, with no Stancl, auth, workspace, cloud driver, backup, restore, move saga, or provider operation.

The durable and ephemeral roots are trusted deployment configuration under `storage_path('app/corex/durable')` and `storage_path('app/corex/ephemeral')`. Each handle is scoped below `tenants/{account-id}`. Absolute paths, `..`, control/NUL bytes, encoded traversal that becomes traversal, backslash/drive paths, source-and-destination escape, and symlink escape are rejected. A retained A handle under B, or outside its original context, fails closed; it never silently follows B.

`usageBytes()` reports non-negative logical durable usage only. The local adapter advertises `export=false`; `exportArchive()` throws `StorageCapabilityUnavailable`. This deliberately defers archive generation rather than adding a ZIP dependency, creating placeholder archives, or implying backup/restore capability. A later adapter may set `export=true` only with its own exact driver and public-API qualification.

Add the direct runtime requirement `illuminate/filesystem: ^12.0|^13.0` to `corex/core`. Do not add an explicit Flysystem, S3, path-prefixing, ZIP, or cloud dependency in P2.1. Laravel's declared filesystem package is the only accepted construction surface; transitive availability is not relied upon as the package contract.

Accept the P1.2 package-test harness binding: `packages/core/tests/Pest.php`, root `tests/Pest.php`, and a `CorePackage` suite for `packages/core/tests/Storage` in root `phpunit.xml`. No new test namespace, package script, root test dumping, or CI workflow change is authorised. The existing `CoreXTestCase`, temporary roots, and no-migration fixtures are the test base.

## Scope binding

Before P2.1 implementation, amend its declared `Files` to include exactly `packages/core/tests/Pest.php`, `tests/Pest.php`, `phpunit.xml`, `packages/core/composer.json`, and root `composer.lock`, then regenerate and verify `bundles/P2.1.json`. The export Action is intentionally not admitted by this decision because export is unavailable in the boxed local adapter.

The required proof covers context-only account derivation; A/B isolation across read, write, list, copy, move, URL and delete; source/destination traversal and absolute-path rejection; symlink rejection; durable/ephemeral separation; zero durable usage; unsupported export; and a core-only no-Stancl/no-auth boot. The existing P2.1 quality gates remain mandatory.

## Почему

The contract remains useful to any Laravel application and leaves cloud/archive implementations replaceable, while the local boundary is secure by construction. An explicit capability denial is safer and more honest than a local archive implementation with an undeclared ZIP/runtime surface.

## Consequences

This decision resolves P2.1's `PUBLIC-API`, `FS-DRIVERS`, and the specifically described `DEPENDENCY-SCOPE` change only. It grants no authority for P2.2, cloud storage, real S3, external operations, tenancy implementation, workspace support, provider credentials, backup/restore/move workflows, or any other D2 gate. P2.1 still requires the scope/bundle refresh, current source checks, implementation, negative tests, and its declared validation before it can close.
