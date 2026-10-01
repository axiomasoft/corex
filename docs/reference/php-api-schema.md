# PHP source surface schema

`CoreX\Attributes\Tooling\SurfaceScanner::scan($root)` owns source discovery, ownership,
classification, signatures and diagnostics. It parses production Composer PSR-4/PSR-0/classmap/files
paths and configuration under `packages/` and `products/`. Products are consumers, without
package API manifests. Inspected files are never executed. Vendor source resolves type references for diagnostics. External members are never expanded
into a package manifest; inheritance and trait members expand only within the same Composer owner.

Runtime metadata has no parser dependency: `#[Api(since: '1.0')]` and
`#[Spi(kind: SpiKind::Implement|Call, since: '1.0')]`. Exactly one annotation per class,
interface, trait or enum. Labels do not inherit. `since` accepts `1.0` or numeric SemVer;
it does not claim a release already exists.

## CLI

```bash
scripts/php84 php scripts/php-api.php --report --format=json
scripts/php84 php scripts/php-api.php --strict
scripts/php84 php scripts/php-api.php --write
scripts/php84 php scripts/php-api.php --strict --check
```

`--root=DIR` selects a source tree. Report exits 0 with classification debt; strict exits 1
on diagnostics. Check exits 1 on stale manifests/docs. Malformed PHP, invalid configuration
and duplicate ownership exit 2 in every mode. Write changes only per-package `resources/contracts/php-api.json`
and `docs/reference/public-api.md`; it never labels declarations or proves strict success.
Write and check are mutually exclusive.

## Records

`php-api-report/1` contains `owners` (all discovered FQCN → Composer owner), `manifests`
(package → manifest), and `diagnostics` with `code`, relative `file`, `line`, `symbol`,
nested `path`, and `message`.

Each `php-api/1` manifest contains `schema`, `package`, sorted `symbols`. Symbol fields:

- `kind`, `layer` (`api`, `spi-call`, `spi-implement`), `since`, `final`, `readonly`, `abstract`;
- `extends`, `implements`, `uses`: separate sorted lists of direct FQCN parents, interfaces and traits;
  normalized type-bearing `doc` tags;
- `methods`: visibility, static/final/abstract/reference flags, ordered parameters with names,
  types, reference/variadic/optional flags, normalized source defaults, return and PHPDoc;
- `properties`: type, readonly/visibility/static, default, PHPDoc and hook signatures;
- `constants`: type, value, visibility, final and PHPDoc;
- `enum_backing` and `enum_cases` (case → normalized source value).

Public members appear in every layer; protected members only in SPI Implement. Inheritance
within the package resolves before filtering; an own override keeps its own signature.
Promoted properties are included independently of constructor visibility. Native types are resolved strings. PHPDoc ASTs resolve nested
generics, shapes, aliases, templates, virtual members and throws, then render canonical types.
Diagnostic paths retain nested AST edges. Ordinary prose and method bodies are omitted.
Unresolved names, malformed tags, cycles and unsupported trait adaptations produce diagnostics.
Model extension SPI requires `Laravel` placement and a `@corex-model-extension` class doc tag;
foreign Internal types remain forbidden.

JSON uses UTF-8, stable ordering and a final LF. No timestamp, absolute path, Git SHA or
whole-source hash enters a manifest. Names, defaults, enum values and types affect snapshots;
body/prose-only changes do not.

## Strict enforcement

`composer php-api` runs `--strict --check` in CI. Report remains diagnostic-only.
The package graph shares source membership and verifies manifest/document freshness;
namespace metadata and the old undeclared-surface baseline are retired. Unknown symbols
cannot be admitted by generated JSON. Module/dependency/ownership gates remain independent.
`SurfaceReport::encode()` and `documentation()` supply the same bytes to CLI and graph consumers.
