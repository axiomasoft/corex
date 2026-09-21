---
id: D57
date: 2026-09-20
status: accepted
item: P11
items: [P11, P11.1, P11.2, P11.3, P13]
supersedes: [D5]
superseded_by: null
---
# D57 — Point-specific identity/order и один canonical fingerprint

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:— ни один из восьми extenders не имеет общего
`priority`; `Menu` уже объявляет `afterHandle`; API и tool имеют свои
semantic identities; `InstalledPackages` сейчас теряет versions/references.
RAG:✅ P11-Q1/Q2: Symfony подтверждает compile-before-use и ordering
компиляционных шагов, но не задаёт identity policy приложения.

## Решение

D5 заменяется этим полным контрактом:

- каждая point имеет поимённую identity из `research/P11-design.md` §2;
- duplicate identity отвергается до projection; диагностика называет
  обоих owners;
- replacement только через manifest directive target→replacement в той же
  point; target и replacement существуют, replacement принадлежит автору,
  graph ацикличен;
- default order — id ASC; точка с declared priority идёт `priority DESC,
  id ASC`; `menu.after` идёт stable topological sort с id ASC frontier;
- fingerprint — SHA-256 от canonical `corex-registry/1`: schema/compiler
  version, module name/version/reference, normalized effective declarations и replacement
  provenance;
- timestamp, host path, tenant id, activation epoch и insertion/filesystem order в hash не
  входят;
- cache artifact с неизвестной schema, missing fingerprint или hash mismatch
  не используется.

## Почему

Без point-specific identity общий `priority` ломает естественную семантику
menu/API/routes. Без module references два dev-build с одинаковой версией
могут дать один fingerprint. Без canonical bytes hash меряет порядок
обхода, а не смысл snapshot.

## Consequences

`ReadRegistryIntrospection` перестаёт иметь свой revision algorithm. P13 пинит
upstream registry fingerprint в API profile вместо копирования declarations.
