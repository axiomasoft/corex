---
id: D51
date: 2026-09-20
status: accepted
item: P10.3
items: [P10, P10.1, P10.3, P10.6]
supersedes: []
superseded_by: null
---

# D51 — Composite catalogue merge явный, cache не authority

**Actor:** plan-designer/codex-gpt-5.6-sol/high

**Evidence:** `RAG:—` текущий provider либо выбирает module catalogue, либо полностью
выключает attributes; политики конфликта и provenance нет.

## Решение

Static/module/database sources сливаются в заданном порядке. Шесть конфликтов имеют
различимые причины; default — reject, кроме byte-identical merge. Shadow retirement
допустим только точечной policy записью. Cached facade обслуживает reads; write,
SchemaOperations и backfill всегда читают uncached authoritative composite.

## Почему

Last-write-wins скрывает drift, а stale cache не должен разрешать мутацию по старой
схеме. Точечная precedence оставляет provenance проверяемым.

## Consequences

Config задаёт уникальный source order и policy version. Cache key включает scope и
source fingerprint; corruption эвиктится fail-closed. Все шесть причин получают
отдельные negative tests.
