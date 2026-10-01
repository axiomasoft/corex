# Index — 2026.09.27-№1-COREX-KERNEL (PLAN3)

| Файл | Роль | Используется фазами |
|:--|:--|:--|
| `brief/00-brief.md` | дословные сообщения владельца, требования B1–B10 | все |
| `findings/00-audit-verification.md` | вердикт по каждому тезису аудита + ➕ пропущенное | все |
| `research/01-external-research.md` | синтез ресерча R-01…R-11 | все |
| `research/02-target-architecture.md` | целевая архитектура (норма по D01–D23; финал — `research/04-architecture.md` после P00.2) | все |
| `artifacts/research-rag/` | дословные captures perplexity/HTTP (провенанс) | — |
| `artifacts/P00-design/facts/` | сырые факты репозитория для P00 | P00 |
| `research/P00-design.md`, `research/P00-design-rag.md`, `artifacts/P00-design/examples.md`, `artifacts/P00-design-rag/README.md` | досье дизайна P00 | P00 |
| `research/P01-design.md`, `research/P01-design-rag.md`, `artifacts/P01-design/{examples.md,facts/,scripts/}`, `artifacts/P01-design-rag/` | досье дизайна P01 | P01 |
| `research/P02-design.md`, `research/P03-design.md` (+ `-rag.md`), `artifacts/P0{2,3}-design/`, `artifacts/P03/` | досье дизайна P02/P03, карта модулей P03 | P02, P03 |
| `research/P04-design.md`, `research/P04-design-rag.md`, `artifacts/P04-design/{examples.md,input-manifest.json,classification.tsv,error-code-map.tsv}`, `artifacts/P04-design-rag/README.md` | досье, поимённые вердикты, ошибки и провенанс P04 | P04 |
| `research/P05-design.md`, `research/P05-design-rag.md`, `artifacts/P05-design/{examples.md,facts/,scripts/p05-facts.sh}`, `artifacts/P05-design-rag/` | досье, внешние факты, примеры, инвентарь и captures P05 | P05 |
| `findings/01-hosted-ci-debt.md` | реестр отказов hosted CI на базе P04 (H1–H12), вход P10 | P10, P05 |
| `research/P10-design.md`, `research/P10-design-rag.md`, `artifacts/P10-design/{examples.md,facts/}`, `artifacts/P10-design-rag/` | досье, внешние факты, примеры, логи и воспроизведение P10 | P10 |
| `findings/P05-P10-design-audit.md` | design audit finish P00–P05 + P10 (ATTENTION, A1–A4); правка — D76 | P10, P05 |
| `decisions/D01–D76` | принятые решения | все |
| `research/03-glossary.md`, `research/04-architecture.md` | имена и норма ядра (P00) | P01–P09 |
| `phases/Pnn/Pnn.md` | индекс детализированной фазы (P00–P04 закрыты; P10 и P05 детализированы, P10 исполняется первой) | фаза |
| `backlog/Pnn.md` | skeleton фаз P06–P09 до их дизайна (D24); `P04.md` — указатель | фаза |
| `artifacts/P04-owner-acceptance/` | принятие GREEN finish владельцем, допуск P04.1 (D66) | P04 |
| `artifacts/P04-design-finish/` | finish P00–P04, fingerprint, continuity и validation | P04 |
| `roadmap.md` | карта фаз + execution-sheet | все |
| `session-routing.md` | pool моделей, native launch, review runbook | все |
| `open-questions.md` | открытые развилки и owner-gates | — |
