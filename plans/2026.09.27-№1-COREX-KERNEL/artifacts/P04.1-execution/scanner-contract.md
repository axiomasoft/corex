# PLAN3.P04.1 scanner contract

Executor: codex/gpt-6-astra/high, provider-thread-state. Scope: D63, D64, P04.1.

The source owner is `CoreX\Attributes\Internal\SurfaceScanner`; CLI and generated documentation
consume its immutable SurfaceReport. Metadata classes have no parser dependency. The declared
PHPDoc development dependency was synchronized through Composer with no unrelated updates.

Discovery uses production Composer paths plus configuration, not installed package classmaps.
Named types have unique case-insensitive ownership; products are consumers only. Source ASTs
resolve attributes, imports, native types, inherited signatures, PHPDoc generic/shape/alias/template
and virtual members. Diagnostics carry file, line, symbol and nested path. Source is not executed.
Vendor sources and internal PHP reflection resolve inherited members; unsupported adaptations,
cycles and unknown types fail closed. Public/protected filtering happens after inheritance.

`php-api/1` records preserve parameter names/order/defaults, flags, properties and hooks, enum
backing/cases, constants and resolved PHPDoc. Bodies/prose, absolute paths, timestamps and Git
identities do not enter manifests. CLI report/strict/write/check semantics and field schema are
in `docs/reference/php-api-schema.md`. Report exit 0 is not strict success.

Tests plant classification, metadata, model/collection/internal payload, nested generic/alias,
unknown/malformed doc, consumer implementation, duplicate ownership, stale snapshot, config
class-string, mutation/default/enum, immutable data, inheritance and property-hook cases.
All inspect observable diagnostics or bytes; scanner fixtures live only in random temporary trees.

## Integration repairs discovered by required checks

- `phpunit.xml`: register the new package-local Attributes directory; otherwise root filtering
  never runs it. Composer's two-command test script now passes filters to the first command and
  always runs the separate TasksWorkflow suite. No suite is removed.
- `tests/Arch/CoreZoneTest.php`: seven exact new files join Foundation, whose outward dependency
  allowance remains empty. The module-layout baseline remains exactly 159 records, byte unchanged.
- `scripts/rename-completeness-gate.php`: D63 reclaims precisely the two namespace declaration
  tokens Attributes and Attributes/Internal. Every old custom-fields FQCN/child namespace remains
  forbidden. An isolated Git fixture proves both positive namespace cases and old-symbol negatives.
- `pint.json`: exclude only the immutable, digest-bound P04 design inventory script, which was
  already unformatted at the input commit. Its bytes and input-manifest hash remain unchanged;
  production/test/tooling lint rules and quality ceilings remain unchanged.
- The old namespace graph remains operational; only the three metadata FQCNs join the existing
  public-api list. Source scanner does not replace R1–R5 or trust a previously generated JSON.

Golden delta: exactly seven added Attribute/tooling records, no changed/removed existing record
(after the existing rename projection). Candidate generation used an isolated checkout. Only
php-surface additions are proposed; routes/events/wire and promoted-fixtures remain byte-identical.
Preimages, candidate, and semantic delta are preserved alongside this contract.

The initial full test attempt hit four schema worker timeouts while concurrent analysis and
other host workloads were active. The seven affected tests and the complete serial SQLite run
then passed without application changes. Timings are retained in validation evidence.
