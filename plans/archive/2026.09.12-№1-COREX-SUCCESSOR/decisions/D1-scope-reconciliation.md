---
type: workflow
id: D1
date: 2026-09-12
status: accepted
item: ""
items: [P1.1, P1.2, P2.1, P3.1, P3.2, P3.3]
supersedes: []
superseded_by: null
tags: [corex, plan-v2, successor]
created: 2026-09-12
---

# D1 — Scope admission после COREX-CORE

**Date:** 2026-09-12
**Status:** accepted
**Actor:** plan-designer/frontier/high · gpt-5.6-sol
**Evidence:** RAG:— CoreX tree 82abc18; archive final handoff 2026-07-28; D2 current Brain excerpts. RAG:✅ GitHub primary API 2026-09-12, PR/run/job links ниже.
**Supersedes:** —
**Superseded By:** —

## Решение

Создать новый successor только для трёх подтверждённых test/CI tails и трёх decision-ready owner-debt slices.
НЕ менять прошлые статусы/спеки/журнал/roadmap архивного COREX-CORE.
Не переносить закрытые items; новые задачи описывают только недоставленный delta.
Вариант «переисполнить P1–P3» отвергнут: архив уже терминален. Вариант «включить весь roadmap» отвергнут: продуктовые/год-2 фичи не являются необходимым post-P3 hardening.

## Почему

Минимальный post-P3 delta сохраняет terminal history; alternatives and evidence ниже.

## Evidence matrix

| Исторический кандидат | Проверка на 2026-09-12 | Решение |
|:--|:--|:--|
| P1.27 coverage floor 55, time-gate | scripts/coverage-min-floor.txt = 80; composer test:coverage --min=80; archive P1.27 terminal; core/modules source metric и compensating crm/tasks gates есть | Исключить: выполнено; НЕ повышать floor новым item |
| P1.28 root tests modules-lint | phpstan-tests-lint.neon paths = tests; quality job invokes analyse:tests-lint | Root часть выполнена; только packages/*/tests delta → P1.1 |
| P1.29 lifecycle upgrade/rethrow, parent guard | archive P1.29 terminal; final P1 handoff включает принятую починку | Исключить: терминальная работа, не новый debt |
| P1.30 PHP8.5 и P1.31 boxed8.3 | ci.yml tests matrix 8.3/8.4/8.5 × L12/L13; boxed drop step есть; run 30329663500 все шесть legs success | Исключить: реальные runner proofs уже есть |
| P2.0 tasks split/нейминг | packages/tasks — corex/tasks; packages/crm — flex/crm; architecture source gate существует; head 82abc18 обновил tasks schema | Не повторять split/rename; ни одной продуктовой задачи |
| P2.1 php constraint | packages/tenancy require php ^8.4; root/core ^8.3 | Исключить: scoped constraint уже доставлен |
| P2.1 fork mirror, load/queue reports | tenancy всё ещё upstream dev-master#76e5f9655924271c6c9da54e2d4ccb82c6f71ea0 + jobpipeline rc7; packages/tenancy repositories только core path | Reproducible release-source ownership → P3.3; load/GA NFT и upstream report deferred вне текущего hardening |
| P2.3/P2.12 template schema hash | rg schema_hash/schemaHash в tenancy/core src+migrations = 0; root_templates migration и DB_SCHEMA не имеют hash | Реальный authority debt → P3.2, НЕ тихая migration |
| P2.6 workspace Move/Rename | packages/tenancy/src/Workspaces имеет resolver, не Move service; архив требует только при потребителе | Deferred consumer-driven; P3.2 фиксирует ownership, не выдумывает сервис |
| P2.8/P2.20 dept/dept_tree | ScopeResolverTest builder positive ltree есть; parity test Scope×Principal creates records with nonexistent users; matchesDepartment — отдельная ветка | Builder долг исключить; positive record + parity fixture delta → P2.1 |
| P2.9 cascade_view | ScopeResolverTest положительный/отрицательный cascade_view и ScopePredicate shared predicate существуют | Исключить: выполнено |
| P2.6/P2.12/P2.23 WorkspaceScope generics | P2.24 terminal; tenancy job PHPStan step exists; latest run tenancy-tests success | Исключить: не снимать @implements под новый major |
| P2.14 legacy row tenancy | legacy row-level scaffolding отсутствует; core contracts + tenancy adapter — DB-per-account | Исключить: никаких legacy-based features |
| P2.15 anti-weakening | объединённый счёт package/root, base-anchored allowance; live job success | Исключить: старое красное состояние не актуально |
| P2.16/P3.6 docblock citations | P2.16 terminal; оставшиеся runtime exception/PHPStan diagnostics содержат private B/AC citations и exact-message tests | Не повторять docblock sweep; runtime wording остаётся explicit deferred owner choice, P3.1 классифицирует, не меняет строки |
| P2.17 product literals + mutation drift | source gate 4 rules; P2.19 reanchors terminal; scripts/mutations — единственный дом | Исключить code cleaning/reanchor; текущий mutation runner proof проверяет P3.3, не пересоздаёт патчи |
| P2.18 duplicate vault mutations | archive mutations/ отсутствует; scripts/mutations has 30 patches | Исключить: выполнено |
| P2.24 L12 promise | tenancy requires ^12|^13; testbench ^10|^11; tenancy-tests no matrix or L12 constrain | Подтверждённый executable delta → P1.2 |
| crm/tasks compatibility symmetry | package constraints ^12|^13, jobs unmatrixed; root tests не включают package suites | P3.3 proof/packaging proposal; flex/crm изменение вне scope L0; не обещать tested L12 для пакетов |
| auth compatibility | auth ^12, testbench ^10 | Не расширять до L13: новое обещание требует отдельного owner outcome; записать в release proposal |
| OQ-6 schema catalogue counts/driver-local table | current DB_SCHEMA root_ count still 18; field-level includes 20; no sys_pubsub_messages | P3.1 owner-ready correction, НЕ редактирование чужого контракта |
| ADR-003/ADR-004 «не переписаны» | current ADR-003 titled UUIDv7 accepted; ADR-004 DB-per-account accepted; stale TODO in ADR-004 still cites old ULID state | Исключить полную ревизию ADR; только stale cross-reference в P3.1 |
| OQ-19 custom-domain verifier | Domain model token/date fields; HostTenantResolver reads verified_at; lifecycle writes only platform verification; CNAME/TXT/ask service отсутствует | P3.2 responsibility/security proposal; external edge/DNS не входит в L0 implementation |
| OQ-20 single-account bootstrap | TenancyServiceProvider unconditional Stancl manager binding; host init gated; no single_account.enabled | P3.2 profile-owner gate; не менять public TenancyManager или invent product default |
| OQ-21 privileged role write | RoleAssignment model/readers есть; пакетный assignment writer отсутствует; GrantPrivilegedRole assertion seam отдельно | P3.2 L0-versus-app authority/security decision; не считать runtime debt закрытым |
| P3.1–P3.7 «живого CI нет / billing» | run 30329663500 root/PG/tenancy/quality/layer/coverage/anti-weakening/crm/tasks success | Исключить blanket billing/rerun task; exact auth gap остаётся |
| PR #13 открыт | GitHub PR13 MERGED, mergedAt 2026-07-27T17:42:13Z | Исключить merge/close/rerun старого PR |
| P3.2 auth distribution | auth-tests job 90183015440 failure at Install dependencies; repo secrets list empty; auth composer includes nonexistent clean-clone sibling path + private vcs | P3.3 конкретный install/auth owner debt; отсутствие repo secret не доказывает отсутствие org secret, причина failure пока не сведена к токену |
| split workflow/isolation install | .github/workflows содержит только CI, split workflow отсутствует; core deps @dev и auth private channel | P3.3 approved topology/proof contract; не создавать зеркала/теги/Packagist в этой сессии |
| compatibility.json/PACKAGE.md | файлов нет; governance §4.9 explicitly says tooling not designed and candidate future ADR | P3.3 выбрать минимальный pre-release evidence contract; не вводить SSOT generator без нового ADR/owner gate |
| registry why/MCP extension_points | ADR-011 требует старый flex:modules:why; ModulesServiceProvider регистрирует compile, why/MCP implementation отсутствует | Deferred: consumer/API need and agentic-surface design не установлены; не менять Contracts и не строить MCP server ради долга |
| ADR-021 Webhooks/ADR-015 Marketplace | proposed year2 / superseded by RegHub | Исключить из successor: product/cross-ecosystem/future scope |
| retrospective missing write-site codes/WARN/roadmap counters | archive terminal read-only; forward-only carve-out accepted | Исключить навсегда из implementation; не «чинить» историю под Layout v2 |
| operator golden DB refresh | archived P2.17 owner operational step; локальный prod/dev state этой design сессией не проверен | Не запускать migrate:fresh и не объявлять выполненным; отдельная operator authority |

## Primary external evidence

GitHub API: repos/axiomasoft/corex/pulls/13, actions/runs/30329663500/jobs, actions/secrets (names only).
PR: https://github.com/axiomasoft/corex/pull/13
Run: https://github.com/axiomasoft/corex/actions/runs/30329663500
Auth job: https://github.com/axiomasoft/corex/actions/runs/30329663500/job/90183015440
Web fetch этих private endpoints недоступен; authenticated gh API дал первичные read-only данные.
Логи старого run не вернулись: нельзя приписывать failure единственную причину. P3.3 требует нового минимального clean-clone reproduce.
Runner proof относится к f953716, НЕ к текущему 82abc18; новый relevant delta проверяется исполнителем.
Mutation job последнего push-run skipped по event condition: это не RED и не свежий 30/30 proof.

## Historical integrity

Archive manifest fingerprint (sorted absolute file paths + sha256sum, UTF-8 output then sha256sum):
`0d4e6d3cd17ba11250a482489dabd52d1e624f06866c1888a48dc8936431e448`.
Полный per-file manifest удержан для design verification; файл архива не изменялся.
Brain status CoreX: docs diverged; docs-public/plans in-sync before design. Docs sync этой сессией запрещён.

**Consequences:** шесть новых items; исключённые terminal/history/product debts не превращаются в новые задачи. P3 proposals сохраняют unresolved runtime debts до accepted owner D# + отдельного executable spec.
