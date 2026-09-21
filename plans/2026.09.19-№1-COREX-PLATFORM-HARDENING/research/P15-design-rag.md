# P15 — RAG: safe rename boundaries

**Снято:** 2026-09-20. **Метод:** один успешный Perplexity browser MCP
запрос после одного timeout; прямая сверка Composer schema и Laravel 13
docs. Точные запросы, статусы и ответ — `artifacts/P15-design-rag/`.
Внутренние имена CoreX в Perplexity не передавались.

| Evidence | Verdict / применение |
|:--|:--|
| PHP `class_alias` | RAG:✅ Alias работает на уровне class identity; не алиасит property/parameter/package/persisted value. P15 не использует его как доказательство BC. |
| Composer `replace` | RAG:✅ Solver metadata, не code/data migration. P15 не добавляет `replace` автоматически; только отдельное owner decision при доказанном external consumer. |
| Laravel Artisan | RAG:✅ Signature задаёт CLI name/arguments/options; discovery и explicit class registration остаются FQCN surfaces. `corex:modules:why` пинится byte-for-byte и проверяется через list/help/call. |
| Persisted FQCN | RAG:✅ Alias не мигрирует строку. RAG:— `mod_*` хранит composer module id, не module-class FQCN; P15.1 доказывает отсутствие DDL/backfill. |

## Граница доказательств

Perplexity подтверждает общие поверхности совместимости, но не выбирает
имена CoreX, gates, пакетные границы или релизную политику. Это локальные
решения D85–D88. Первый MCP вызов timeout, generic web connector
недоступен, а повтор полного PHP manual body не разрешил DNS. Эти
проверки не выдаются за GREEN; успешный MCP ответ и два прямых
первоисточника несут фактическую опору.
