---
id: D08
date: 2026-09-27
status: accepted
item: P05
items: [P05]
supersedes: []
superseded_by: null
---
# D08 — configuration (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Решение

Каждый пакет: свой config-файл/ключ → при boot `…Options::fromArray()` (immutable, typed, fail-fast `ViolationList`) → биндинг по типу → конструкторная инъекция. Static-holder `CoreX\Support\Config` и все static-геттеры удаляются; `config()`/`env()` — только в service providers/командах конфигурации; runtime не перечитывает config. Опасные режимы — только явным флагом, в `production` небезопасная комбинация — исключение при boot. Octane: ни одного static mutable состояния (гейт). `config:cache`-совместимость проверяется в CI.

## Почему

F00 A3.2–A3.5 (43 static-геттера, 41 потребитель, перечитывание на каждый вызов, `config()` в runtime); `pplx-07` (Laravel boundary → typed object, Octane), `pplx-02` (build → validate → freeze); chatom PLAN2 §9.

## Consequences

P05; constitution CFG-* обновляется.
