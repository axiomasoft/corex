# P10 — retrieval provenance

Дата: 2026-10-01. Синтез и границы доверия — `research/P10-design-rag.md`.

| Capture | Tool / source | Вопрос |
|:--|:--|:--|
| `local-01-composer-with.md` | установленный Composer 2.8.2, `composer update --help` | временное ограничение версии без изменения `composer.json` |

Внешний поиск (perplexity, context7) в этой фазе не выполнялся: решения D74 выводятся из логов hosted CI, кода
репозитория и воспроизведения в чистой копии — `artifacts/P10-design/facts/`. Единственный внешний факт (флаг
Composer) подтверждён первичным источником — установленной программой. Поведение раннера GitHub (наличие `rg`,
пакет клиента PostgreSQL 18) проверяется самим hosted CI при исполнении — `VERIFY-AT-EXEC`.
