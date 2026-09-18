# P12 — Package boundary rebaseline design RAG

**Perplexity:** successful
**Search artifact:** `artifacts/P12-design/perplexity-response.json`
**SHA256:** `d02e930c23a3d870b230e0e71d3db47a6f1ce2fbcb908573b4e1a929d2224ded`

RAG:✅ Four successful neutral queries (optional driver registry, package/namespace rename
mechanics, typed configuration objects, closures across durable boundaries); exact query text
retained in `artifacts/P12-design/retrieval-history.json`. One earlier query on container
binding lifetimes timed out with no response and is deliberately excluded from the evidence
set; its relevant claim is carried instead by the typed-configuration response, which
enumerates unsafe singleton state. Tool answers were then checked against primary sources
read directly: six raw bodies with HTTP status, byte length and digests in
`artifacts/P12-design/primary-source-retrieval.json`. One tool claim failed that direct check
and is not used as evidence (see Failed verification). RAG:— every statement about this
repository's own code is verified by reading files and is recorded in
`research/P12-audit-reconciliation.md`, not derived from the web.

## Primary sources

| Origin | URL | Supported claim |
|:--|:--|:--|
| Composer | https://getcomposer.org/doc/04-schema.md | `replace` is documented as a "map of packages that are replaced by this package" — a solver-level declaration that preserves no PHP symbol; `suggest` is documented as "suggested packages that can enhance or work well with this package", the correct home for an optional driver dependency. |
| PHP | https://www.php.net/manual/en/function.serialize.php | "Closures cannot be serialized", demonstrated with the fatal `Serialization of 'Closure' is not allowed`; a `Closure` property therefore cannot cross a persisted boundary. |
| Laravel | https://laravel.com/docs/13.x/queues | `dispatchSync` processes the job during the current process, so a synchronous lane never persists the payload and hides a non-serializable field. |
| Laravel | https://laravel.com/docs/13.x/packages | `mergeConfigFrom` is documented as a call inside the service provider's `register` method — configuration is read at the package composition boundary. |
| Laravel | https://laravel.com/docs/13.x/octane | A singleton resolved during application boot is reused across subsequent requests, which is what makes tenant- or request-derived singleton state unsafe. |
| Laravel (package README) | https://raw.githubusercontent.com/laravel/serializable-closure/master/README.md | The package's own caveats are exactly two: serialization in REPL environments is unsupported, and multiple same-signature closures on one source line may become indistinguishable. |
| PostgreSQL | https://www.postgresql.org/docs/16/sql-prepare.html | "Prepared statements can take parameters: values that are substituted into the statement when it is executed", and every parameter carries a `data_type`. A parameter is therefore a typed data value; a table identifier is not one and reaches the server as statement text. |
| PHP | https://www.php.net/manual/en/pdo.prepare.php | Parameter markers are placeholders "for which real values will be substituted when the statement is executed". Same mechanism statement from a second independent origin. |

## Choice / independent origin mapping

| Choice | Independent origins | What remains local inference |
|:--|:--|:--|
| Hard rename instead of `replace` or a `class_alias` bridge (P12.4, P12.6) | Composer + Perplexity | That these packages have no external consumers, established by reading the repository, not by any source. |
| Optional dependency via `suggest` with a guarded fallback (P12.7) | Composer + Perplexity | Which guard sites count as a working fallback; verified in `AttributesServiceProvider`. |
| Driver registry instead of two providers competing for one binding (P12.5) | Perplexity + Laravel | That `extend()` in `boot()` precedes first resolution of a `scoped` binding in this application's lifecycle. |
| Fail loudly on a selected-but-broken driver; disable only as a documented default (P12.5) | Perplexity + Composer | The specific exception taxonomy and message wording. |
| Configuration read once at the provider into an immutable typed object (P12.7) | Laravel + Perplexity | That the current default table names must stay byte-identical, which follows from the frozen local DB contract. |
| No `Closure` in an object crossing a durable boundary (P12.8) | PHP + Laravel | That `DataScope` actually crosses a queue boundary, established by reading its consumers. |

## Local selection / proof boundaries

The external sources establish mechanism, not application. Composer defines what `replace`
and `suggest` mean; whether a rename is safe here depends on the absence of external
consumers, which is a repository fact. PHP and Laravel establish that a `Closure` cannot
survive a persisted queue payload and that a synchronous lane hides this; whether
`DataScope` reaches such a payload is a repository fact. Laravel establishes that
`mergeConfigFrom` belongs in `register` and that boot-time singletons are reused across
requests; which of this repository's bindings hold tenant-derived state is a repository fact.

No source grants authority over a frozen contract, and none proves that a local test passes.
In particular, no source was or could be used to justify a schema change: P12 keeps every
migration directory untouched and preserves current table names as configuration defaults.

## Failed verification

The tool answer asserted that the `laravel/serializable-closure` README rules out closures
referencing objects with `readonly` properties, and additionally lists anonymous classes and
attributes inside such closures as unsupported. Direct retrieval of that README (HTTP 200,
2882 bytes, digest recorded in `primary-source-retrieval.json`) contains no occurrence of
`readonly`, and its "Caveats" section lists exactly two items, reproduced in the primary
source table above. The claim is therefore rejected and is used nowhere in the design.
This matters beyond bookkeeping: the rejected claim was the most convenient argument against
`laravel/serializable-closure`, and P12.8 must not rest on it. The retained argument is that
transportability of behavior is not authorization semantics — a signed payload proves the
callback data was not modified, not that the actor may perform the operation now. That is
local inference, labelled as such, standing on the verified PHP and Laravel facts.

A second tool retrieval, on whether a bind parameter can carry a table identifier, timed out
with no response and is excluded from the evidence set. The premise was instead established
directly from the two primary sources added above. Their normative text states that a
parameter is a substituted typed *value*; neither page states in its normative body that an
identifier is rejected as a parameter. The only explicit statements to that effect on the PDO
page live in user-contributed notes, which are not primary documentation and are not cited.
The design therefore claims only what the sources support: a table name is not a bind value
and reaches the server inside the statement text, so a table name taken from configuration
must be validated as an identifier before use. That an unvalidated identifier is exploitable
here is local inference from the repository's own query construction, labelled as such.

## Alternatives

**Architecture-test engine.** `qossmic/deptrac` was rejected because the package is abandoned
and moved to `deptrac/deptrac`; both it and `phpat/phpat` were then rejected as a new dev
dependency and a second analysis DSL, because this repository already carries a working
executable-gate pattern (`scripts/contract-guard.sh`, `scripts/anti-weakening-gate.sh`,
`tests/Arch/LayerTest.php` with `tests/Support/SourceScanner.php`). Selected: a repo-local
gate script following that pattern. Failure mode: a hand-built class-to-package index can
diverge from PSR-4 resolution. Mitigation: longest-prefix matching as PSR-4 does, and a
mandatory file/line/import/owner address on every violation, so a red gate is actionable
and a silent pass is impossible.

**Namespace compatibility on rename.** `replace` was rejected because it is solver-only and
preserves no PHP symbol; a `class_alias` bridge was rejected because it exists for a
namespace already consumed externally. Selected: hard rename via `git mv`. Failure mode: a
missed reference outside `src/` — CI workflows, docs, install profiles, profile lock files.
Mitigation: a repository-wide `rg` sweep whose empty output is a closing condition.

**Search topology.** Merging the Meilisearch adapter into the base package (audit 1) was
rejected because it would pull the SDK to every consumer; leaving the competing bindings in
place was rejected because provider load order decides the winner. Selected: keep the adapter
package, register a driver in a manager owned by the base package. Failure mode: `extend()`
arriving after first resolution. Mitigation: register in `boot()` against a `scoped` binding.

**Attributes packaging.** Splitting into three packages (audit 2) was rejected because the
demonstrated defect is the mandatory dependency, not the package count, and no consumer
justifies the combinatorial growth. Selected: one package with `corex/modules` moved to
`suggest` over the existing guarded fallback. Failure mode: the fallback silently degrading a
production install. Mitigation: the fallback is an observable typed unavailable state, the
allowlist entry names the guard site, and the README states what is lost.

**Configuration.** Leaving `config()` inside storage engines was rejected for hiding the
dependency and deferring validation; passing one large options object into every class was
rejected as a disguised service locator. Selected: a narrow immutable `AttributeTables`.
Failure mode: configuration defaults drifting from the real table names and so from the
migrations. Mitigation: defaults are transcribed from the current hardcoded strings
character for character, with an empty migration diff as a closing condition.

**Plan mechanics.** Reopening P2–P7 (audit 2's "P0R as predecessor") was rejected: those
phases are terminal and reentry requires a demonstrated contract conflict, which does not
exist. Freezing all feature work (audit 1's stage 0) was rejected as contradicting the live
plan. Selected: a new phase P12 blocking P8.

## Cross-phase impact

P1–P7 are not reopened; P12 changes none of their accepted contracts except one item carried
under G-API. P8–P11 are blocked until P12 seals, because P12 changes two package names
(`corex/transfer`, `corex/search-intl`) and the way `SearchProvider` resolves; building on
the old names would create rework. The frozen local DB contract is untouched: table names
move into configuration with identical defaults and no migration is edited. D40's Meilisearch
SDK pin stays in force — relaxing it needs a separate dependency receipt and is deferred to
an ADR by P12.9, together with the confirmed-but-unfixed defects (patch presence semantics,
transfer runner decomposition, `config()` in the domain layer, tenant-dependent singletons).

## Unresolved premises

Version-state claims were checked on 2026-09-18 and need rechecking if P12 executes much
later. Whether each of the twenty packages has a runnable test target is an execution fact
for P12.2, which must disclose packages without a `tests/` directory as explicit coverage
holes rather than closing them with invented tests. Whether the pg lane is reachable in the
executing environment is likewise an execution fact; an unreachable lane is disclosed as an
environment limitation and never reported as green.
