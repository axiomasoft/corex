---
id: D01
date: 2026-09-27
status: accepted
item: P02
items: [P02, P03, P08, P09]
supersedes: []
superseded_by: null
---
# D01 — compat (accepted)

**Actor:** owner 2026-09-27 (`brief/00-brief.md` REPOSITORY-OWNER-MESSAGE)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Solution

Совместимость не сохраняется: без `class_alias`, deprecated-мостов, `replace`/`abandoned`, upgrade-гайдов и migration path. Имена, пакеты, namespaces, таблицы, config-ключи, wire-поля, версии меняются, если это приближает к целевой архитектуре. Перенос = перемещение + правка всех ссылок + тесты + docs в одном item; остаточная ссылка на старое имя — дефект. Первый публичный релиз — `1.0.0-rc.1`. Решения прошлых планов (D32 «FQCN не меняются», D82 и т.п.) не ограничивают PLAN3, если противоречат этому D.

## Why

Владелец: «неприкасаемого кода нет … код и версии можно менять как угодно». Публикаций нет: Packagist vendor `axiomasoft` пуст, `corex/*` принадлежит чужому владельцу (`http-01`); тег `v0.1.0` — только в приватном GitHub.

## Consequences

Отменяет D32-запрет смены FQCN и совместимые переходы прошлых планов; P02/P03 — массовые переносы инструментом.
