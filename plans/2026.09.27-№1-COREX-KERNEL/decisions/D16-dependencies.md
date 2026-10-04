---
id: D16
date: 2026-09-27
status: accepted
item: P01
items: [P01, P07]
supersedes: []
superseded_by: null
---
# D16 — dependencies (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Solution

Root `minimum-stability: stable`; в релизных пакетах нет VCS/`@dev`/alpha зависимостей; межпакетные ссылки — `self.version`-совместимые диапазоны lockstep. Жёсткая зависимость capability→capability допустима только если без неё пакет бессмыслен; иначе — контракт + optional integration (`suggest` + условная регистрация). `notifications ↛ wakeup` (сейчас 10 импортов) — через контракт ядра. `composer-dependency-analyser` на каждый пакет (shadow/unused deps — ошибка).

## Why

F00 A2.2, A12.1; `pplx-02` (optional adapters), `pplx-10`; B8 «модули максимально независимы».

## Consequences

P01 гейт; P07 развязка notifications/wakeup.
