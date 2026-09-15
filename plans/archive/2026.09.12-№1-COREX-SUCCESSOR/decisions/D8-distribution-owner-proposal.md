---
type: workflow
id: D8
date: 2026-09-12
status: accepted
item: P3.3
items: [P3.3]
supersedes: []
superseded_by: null
---

# D8 — Isolated distribution: minimum proof contract and pending release authority

**Date:** 2026-09-12
**Status:** accepted
**Actor:** gpt-5.6-sol/high · plan-run
**Evidence:** RAG:— D1–D5; current manifests/locks/workflow on worktree based on `8b11672a1e1f14df0157404d041ce4e8f0aa2a23`. RAG:✅ official Composer docs and authenticated primary GitHub API verified 2026-09-12, linked below. Local safe auth reproduce: PHP8.4.1, Composer2.8.3, exit1 before private repository authentication.
**Supersedes:** —
**Superseded By:** —

## Решение

Принять distribution proposal evidence и reproducible failure boundary. Publish authority, target repositories/org, AzGuard credentials/channel/ref, stancl source ownership и compatibility-tooling ADR остаются pending. Нет split export, install-success promise, fork/tag/release/push/secret/Packagist изменения. Private dependency не экспортируется.

## Почему

Monorepo path installs и local auth vendor не моделируют чистого потребителя. Release contract должен сначала доказать artifact graph, затем применяться только после exact owner authority.

## Composer graph and root responsibility

| Package / candidate | Direct graph from manifest | Consumer source responsibility | Actual evidence / gap |
|:--|:--|:--|:--|
| corex/core / public candidate | php^8.3; brick/money^0.9 or^0.10; illuminate^12 or^13 | Released core provider channel; public upstream providers | Historical root six CI legs successful; no isolated split install proof |
| corex/modules / public candidate | php^8.3; core@dev; illuminate^12 or^13 | Root can resolve approved core/modules versions; no `../core` | Root harness proof; provider discovery/morph-map smoke still required in pristine consumer |
| corex/tasks / public candidate | php^8.3; core@dev + modules@dev; illuminate^12 or^13 | Same released lower graph, selected module discovery | Package CI PHP8.4 unconstrained; L12/L13 per-package symmetry missing; not inferred from root |
| corex/tenancy / public candidate with explicit prerelease limitation | php^8.4; core@dev; firebase/php-jwt^7.1; stancl dev pin as4.0.0; jobpipeline rc7; illuminate^12 or^13 | Root owns stancl source/channel/stability and any pin/alias, plus core provider | D4 local isolated L12/L13 176/496 each; new matrix no live runner proof |
| corex/auth / opt-in PRIVATE channel | php^8.3; core@dev + modules@dev; AzGuard^0.2@dev; illuminate^12 | Root declares approved private AzGuard VCS/registry AND credentials; root permits necessary dev stability | D5 local sqlite33/54, PG106/275; historical auth CI install failure; no L13 promise |
| flex/crm / excluded | core/modules + illuminate^12 or^13 | Consumer ecosystem channel, outside CoreX L0 split | Unmatrixed PHP8.4 package proof; no crm changes/rename under this plan |

Root monorepo `repositories: packages/*` and root inclusion of flex/crm are development composition, never released consumer fixture. Core has no upward dependency. Initial public candidate allowlist exactly core/modules/tasks/tenancy; auth only separately approved private/opt-in artifact, never default public graph; crm excluded. No candidate is already publication-ready by this table.

RAG:✅ dependencies' repository declarations are ignored; consumer ROOT owns extra source declarations. Path repositories reference local directories and may symlink. [Composer repositories](https://getcomposer.org/doc/05-repositories.md#repository), verified 2026-09-12. Consequently auth's private VCS entry does not transitively configure a consuming application; nor do package-local core paths supply published versions.

RAG:✅ stability flags and dev `#ref` overrides are root-only; dependency pin is ignored by consumer resolution, and pin checkout does not freeze branch metadata. [Composer package links](https://getcomposer.org/doc/04-schema.md#package-links), verified 2026-09-12. `@dev` is permission for unstable resolution, not a provider channel or proof. Public release must provide resolvable lower-package versions/approved stability and a reproducible stancl source. Prefer immutable owned tagged metadata; temporary dev-root pin needs explicit root requirement, lock and source-reference verification, and still has metadata-drift limitation.

## Concrete auth reproduce and next diagnostic

Fresh temporary directory held only copied auth/core/modules manifests in `packages/{auth,core,modules}`. No vendor/lock/source checkout or sibling azguard; fresh COMPOSER_HOME/cache; COMPOSER_AUTH/provider-token variables removed; network disabled. Command in auth package: `composer update --dry-run --no-dev --no-scripts --no-plugins --no-interaction --no-progress`. Exit1: path repository `../../../azguard/packages/core` does not exist (PathRepository.php:163). Core/module paths exist in this fixture; failure is independently AzGuard sibling path, before VCS credential resolution. This is a diagnostic fixture, not pristine consumer success or definitive historical CI root cause.

Historical [auth job 90183015440](https://github.com/axiomasoft/corex/actions/runs/30329663500/job/90183015440) API still reports Install dependencies failure on SHA `f95371624e90b921753295a3f58bb47f5d0b2af3`. Run conclusion failure; no current log proves a unique token cause. No repository-secret value was read; org/environment secret availability not inferred. Package-local auth lock uses AzGuard dev-main path dist, no immutable source ref: local installation cannot choose a private release pin.

Next safe diagnostic after channel/ref approval: disposable auth-as-root fixture removes only its local development path overrides, declares consumer-root sources, injects a narrowly authorized read credential without logging, resolves exact approved immutable AzGuard metadata/ref, then compares installed source/dist references. Separately test auth-as-dependency consumer with root VCS/auth present and absent; expected clean error without credential. Do not fabricate credential/tag or run private authenticated install before authorization. Keep development override outside released manifest/artifact; no vendoring or publishing AzGuard.

## Immutable stancl source and current discrepancy

Declared tenancy source: [archtechx/tenancy commit 76e5f9655924271c6c9da54e2d4ccb82c6f71ea0](https://github.com/archtechx/tenancy/commit/76e5f9655924271c6c9da54e2d4ccb82c6f71ea0), API existence verified now. Package lock has that ref; root lock has **553f57a8adb62ff33e54c99281e7fafd516aab4f**. This is a concrete graph/pin evidence gap, consistent with root-only override limitation; not repaired here. No recommendation to move pin or claim upstream GA status.

Jobpipeline [v2.0.0-rc7](https://github.com/archtechx/jobpipeline/tree/v2.0.0-rc7) primary git-ref API resolves to `07dc54a49cf9e2df1238ee4a347af333260519a6`, matching current locks. Release proof must record actual installed refs and metadata digest, not only version alias. Owner chooses an owned mirror/tag with fixed metadata versus temporary explicit consumer-root pins/lock and accepted drift limitation. No mirror/fork created and no third-party pin changed.

## Future split workflow and pristine acceptance fixture

1. Approved source candidate SHA and allowlist → deterministic dry-run per-package export in builder. Package root contains its composer.json/src/config/migrations/docs/license, no monorepo credential/config/vendor, sibling paths or private dependency contents. Map source SHA→export tree/ref + artifact digest. Tool version/checksum, export exclusions and archive licensing included in later spec; local dry-run does not push/tag.
2. Builder exposes only sealed package zip/dist metadata to isolated consumer environment; no source checkout or builder filesystem mount. Consumer starts from a minimal supported Laravel application, empty vendor/lock and empty Composer home/cache, no global repositories/auth or sibling paths. Consumer ROOT declares only staged artifact/released providers and approved upstream sources; reject any `type:path`, CoreX source symlink or external development path in resolved graph. A staged artifact may have dry-run version metadata, explicitly labelled unpublished; it is not a release tag.
3. Solve each package independently and approved combinations: core→modules→tasks; core→tenancy; optional private core/modules/auth separately. PHP/core profiles 8.3/8.4/8.5 × L12/13; tenancy8.4/8.5 × L12/13; auth L12 only. Real package Testbench/integration suites use sealed test inputs outside source checkout; root harness alone insufficient for tasks compatibility. Test cold `update`, record lock, then fresh empty-home/cache/vendor `install` from that lock; compare exact refs/metadata/artifact checksums, `check-platform-reqs`, boot/provider/module smoke and affected tests. PG16 tests require isolated `_test` preflight. Run/job/SHA/versions/proof class retained with no tokens.
4. Negative fixtures: missing core/modules provider; consumer lacks private source; no/denied credential; disappearing sibling path; dev pin ignored or wrong installed ref; unsupported PHP/framework; archive contains credential/path/source symlink. Each fails at expected boundary. Positive public graph requires zero private credentials; private failure cannot invalidate public proof by hidden root coupling.
5. GREEN dry-run export + pristine install/test evidence → FINAL owner-authorized push/tag/release step scoped to exact org/repos/refs. Destination mirrors read-only to humans, monorepo authoritative. No source release/tag/fork/Packagist operation automatic after local checks. Split upload token scoped to selected mirrors; separate read-only dependency credential for consumer test; trusted branch execution only. Operator creates/injects secrets after scope approval; no secret material in carriers. Failed proof publishes nothing.

Rollback/exclusions: discard local staging exports and disable future publication job; never force-push/delete tags or mirrors. Published correction requires new approved release. Pin/channel rollback uses previously proven immutable artifact and consumer lock, never unverified branch fallback. DNS/Packagist/fork/secrets changes require separate scoped authority. Current work creates only this proposal/queue.

## Exact owner requests

1. **RELEASE-COREX:** «Подтверждаешь target org axiomasoft и proposed mirrors corex-core/corex-modules/corex-tasks/corex-tenancy с allowlist core/modules/tasks/tenancy и dry-run→pristine proof→отдельно authorized push/tag порядком, либо выбираем private registry/artifact channel без public splits?» Имена/org — proposed, не established state. Approval сейчас выбирает topology/spec; external create/fork/push/tag/release/Packagist/secrets остаются запрещены до exact refs и отдельного разрешения. Acceptance/rollback — workflow steps выше; owner Dmitry/repository publisher.
2. **AUTH-PRIVATE-CHANNEL:** «Выбираем canonical private AzGuard VCS/registry, owner-provided immutable compatible0.2 ref и selected-repository read credentials, сохраняя auth opt-in private, либо auth исключаем из distribution до отдельного release владельца AzGuard?» Рекомендация — canonical approved source с development override вне artifact. Candidate source `https://github.com/axiomasoft/azguard-private.git`; immutable private ref неизвестен, должен предоставить/подтвердить AzGuard source owner. До него fixture non-runnable. No grant to export AzGuard/create secrets by proposal acceptance. Affected: released auth metadata, consumer root, CI auth; acceptance exact-ref cold install and denied-credential negative; rollback opt out of private auth, retain existing private channel.
3. **STANCL-SOURCE:** «Выбираем owner-maintained fixed-metadata mirror/tag исходного76e5f965… плюс jobpipeline rc7 ref07dc54a…, либо temporary explicit consumer-root pin/lock с признанным branch-metadata risk?» Рекомендация — fixed-metadata source after fork/license/ownership approval. Mirror org/repos/name/version unresolved; separate fork/push/tag authority обязателен. Acceptance: manifest/lock/installed refs agree in root and pristine package legs, metadata digest identical; rollback pinned previously proven source, no live DB change. Owner Dmitry, third-party-source maintainer named in future spec.
4. **COMPAT-PROOF:** «Одобряешь minimum per-package factual passport/proof table с constraints, actually tested versions/SHA/runner или local class/private limitations перед публикацией, либо сначала authoring отдельного compatibility.json-generation/enforcement ADR?» Рекомендация — minimum evidence contract first, generator deferred per D2 Governance§4.9. Neither option is authorization to promise authL13 or edit crm. Affected: future package release docs/proof workflow; acceptance every declared major tied to install+package tests (tasks gap visible; D4 not live proof), no generated SSOT claim; rollback docs-only revert/no publishing. Owner Dmitry/Governance.

RAG:✅ Composer GitHub private access requires read-only metadata/contents; fine-grained selected-repository credentials and finite lifetime are the proposed minimum. [Composer private authentication](https://getcomposer.org/doc/articles/authentication-for-private-packages.md#github-oauth), verified 2026-09-12. Credential creation/injection remains operator-authorized future work.

## Acceptance and residuals

D3 lint/D4 tenancy/D5 auth evidence reused at unchanged source boundaries; none proves isolated public/private consumer install or current runner GREEN. Safe path reproduce supplied and external premises freshly checked. Queue has four exact unresolved requests, no answers or invented immutable AzGuard ref. No distribution workflow/generator/runtime/source/schema/public-contract changes. Next implementation needs owner-provenance D# and separate executable spec; P3.3 closes only proposal delivery.
