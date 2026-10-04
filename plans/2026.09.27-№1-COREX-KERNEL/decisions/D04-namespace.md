---
id: D04
date: 2026-09-27
status: accepted
item: P00.1
items: [P00.1, P02, P03]
supersedes: []
superseded_by: null
---
# D04 — namespace (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Solution

Ядро `axiomasoft/corex` владеет корнем `CoreX\` с **закрытым списком** модулей (первых сегментов); пакет `axiomasoft/corex-<name>` владеет ровно одним корнем `CoreX\<Name>\`; сегменты пакетов зарезервированы и запрещены в ядре. **Путь = namespace**: один PSR-4 корень на пакет (`CoreX\<Name>\` → `src/`), PSR-4 folding (несколько каталогов на namespace) запрещён. FQCN однозначно определяет пакет (ownership-гейт). Контракты tenant-контекста ядра уходят из `CoreX\Tenancy\` в модуль ядра с именем из glossary P00.

## Why

F00 A5.2 (коллизия `CoreX\Tenancy` core↔tenancy), A6.3 (folding в 7 пакетах), X1. Короткие FQCN + однозначность через гейт — та же модель, что chatom PLAN2.D22. PHPStan native `@internal` работает по первому сегменту namespace и не отличает пакеты с общим корнем `CoreX\` (`pplx-10`) → нужен собственный ownership-гейт.

## Consequences

P00.1 glossary и карта сегментов; P02 unfold PSR-4 + переносы; P01 ownership-гейт.
