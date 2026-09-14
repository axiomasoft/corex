---
type: workflow
id: D2
date: 2026-09-12
status: accepted
item: ""
items: [P1.1, P1.2, P2.1, P3.1, P3.2, P3.3]
supersedes: []
superseded_by: null
tags: [corex, plan-v2, successor]
created: 2026-09-12
---

# D2 — Source boundaries, нормативные extracts и authority gates

**Date:** 2026-09-12
**Status:** accepted
**Actor:** plan-designer/frontier/high · gpt-5.6-sol
**Evidence:** RAG:— current Brain/CoreX normative files, current FlexCRM DB_SCHEMA/B-11 and CoreX tree 82abc18; source hashes ниже.
**Supersedes:** —
**Superseded By:** —

## Решение

Этот carrier — plan-owned, minimized execution input для решений P3, не замена SSOT Brain docs. Design candidate пока не committed и не execution-qualified; фиксация portable snapshot проверяется при owner-approved design finish.
При изменении source hash получить новые extracts через следующий decision record; не обновлять D2 молча.
Итоги ниже — наблюдения/предложения, НЕ owner approval. Ни один public-contract/schema/profile/release gate не снят.

## Почему

Актуальные Brain источники расходятся с архивными snapshots; перенос verified extracts сохраняет portability и не выдаёт proposal за authority.

## Normative excerpts checked

1. Brain/CoreX/docs/03_Dev/ADR/ADR-001 Stack.md:
   поправка 2026-06-14 = PHP 8.3–8.5, Laravel12–13; поправка 2026-07-03 = core PHP8.4–8.5, Laravel12–13.
   Current user AGENTS и root/core composer = PHP ^8.3, compatibility12–13.
   Нельзя silently drop PHP8.3; owner chooses clarification separating product target and L0 compatibility.
   SHA256 c3bd9e7f4e15249134e36faf48159e87987521e75566cde72120d27879812c44.
2. ADR-003 ULID.md уже accepted «UUIDv7 вместо ULID», BaseModel uses HasUuids; полная миграция ADR не нужна.
   SHA256 624dcedb469f48c2903804552d15185b0db3db2769605ab5464783403056f9cb.
3. ADR-004 Multi-Tenancy.md accepted DB-per-account; §последствия stale TODO говорит, что ADR003 ещё ULID.
   SHA256 8da48e8e47ff6c38a8e579ef953382260e3486559af29faf420707c9b2c74dd4.
4. Architecture_CoreX §1: coverage≥85, PHP8.2–8.4/L11–12; Development_Process §2 example PHP8.2/8.3/L11/12, coverage85.
   Package_Governance package passport example coverage_threshold80; §4 compatibility.json declared SSOT,
   §4.9 generation/enforcement explicitly «не спроектирован… кандидат на будущий ADR, не исполнение сейчас».
   Fact floor80; archive P1.27 metric88.3 is historical measurement, НЕ current measured coverage.
5. Current FlexCRM/plans/flex/60_contracts/DB_SCHEMA.md §2 root_=18; §3.1 20 tables including 3 non-tenancy-owned tables.
   root_templates fields exactly: id uuid PK, version int UNIQUE, db_name varchar63, status building/ready/retired,
   embedding_model varchar64, embedding_dim smallint, git_ref varchar64 nullable, built_at timestamptz nullable,
   created_at/updated_at timestamptz. НЕТ schema_hash. sys_pubsub_messages не перечислена.
   SHA256 9768dfa40e72492f7863ee2a15f36157e20a0810f5eb998125401482a0f9ac98.
6. B-11 §5.7/AC13 custom domains: CNAME+TXT verification, ask200/404, ca_used. Таблицы существуют, write-service нет.
   B-11 §6.1 обещает workspaces в boxed profile, но host disabled и unconditional Stancl manager не инициализируются.
   B-11 §5.9: under support impersonation prohibit privileged-role grants; enum/assert seam есть, assignment writer нет.
   Продуктовые names/hosts из этих источников НЕ копировать в L0 implementations.
7. ADR-008 Contracts frozen/major discipline; archive D17 pre1.0 minor allowance исторический.
   Этот user brief явно строже: ANY public Contracts delta требует отдельного major/owner gate.
8. ADR-002 requires monorepo read-only splits + isolated consumer installation.
   ADR-011 old flex:modules:why and registry.extension_points — absent, но no new MCP/public API approved.
   ADR-015 superseded by RegHub; ADR-021 proposed year2. Это не current hardening deliverables.

## Owner decision questions reserved for P3

| Gate | Exact unresolved choice | Recommended proposal, not authorization |
|:--|:--|:--|
| NORM-COREX | floor80 versus policy85; L0 PHP8.3 versus ADR8.4; outdated process matrix; DB count/driver-local note; stale ADR004 TODO | Clarify effective tested baseline separately from target; owner-authorized forward docs corrections only |
| TEMPLATE-PARITY | add hash to frozen DB_SCHEMA, or explicitly accept ready-only residual risk | Additive hash proposal with canonical hash definition and mismatch/no-write tests; no migration until approval |
| BOXED-INIT | supported generic single-account+workspaces profile or explicit unsupported profile | Parameterized generic profile proposal; no app hostname or product default in corex |
| DOMAIN-OWNER | generic verifier seam versus edge/control-plane ownership | Separate verified-state reader from operator DNS/TLS ownership; no external side effects without authority |
| ROLE-WRITE | generic L0 assignment writer+impersonation guard versus app-owned write path | Pin actual writer and its GrantPrivilegedRole call-site, validate grants under support context |
| RELEASE-COREX | package allowlist, split org/repos/channel, private AzGuard credential/tag, reproducible stancl source | Core/modules/tasks/tenancy candidates only; auth opt-in private dependency clearly documented; flex/crm NOT L0 |
| COMPAT-SSOT | compatibility tooling ADR and passport necessity before publish | No generator scope by inference; minimum accurate install/test matrix, then owner chooses ADR |

## Portable Required Reads policy

P1/P2 code inputs resolve from repository mirror relative to CoreX root.
P3 bundles require D1/D2 and predecessor outputs, not host-specific vault paths or archive closed specs.
Executor verifies source extracts against current normative homes when deciding authority; if foreign meaning changed, gather evidence and emit request, no silent contract choice.
Source paths above are provenance only, not mandatory-read entries or write targets.

**Consequences:** P3 outputs decision-ready D6/D7/D8 + deduplicated owner queue requests. Accepted agent decisions only assert evidence/proposals; owner acceptance requires distinct provenance.
