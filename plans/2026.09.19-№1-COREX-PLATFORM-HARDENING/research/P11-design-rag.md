# P11 — RAG и внешние подтверждения

**Снято:** 2026-09-20. **Метод:** Perplexity MCP с последующей
проверкой по первичным official docs. Точные запросы, ответ,
неудачная попытка и SHA256 лежат в `artifacts/P11-design-rag/`.

| Query | Verdict | Влияние на дизайн |
|:--|:--|:--|
| P11-Q1 Perplexity | confirmed with transport limitation | Symfony даёт compile/cache boundary; Octane и queue workers держат application state в памяти и требуют reload/restart. Первый запрос упал; второй не вернул links, поэтому факты не приняты без Q2. |
| P11-Q2 official docs | confirmed | Snapshot меняется на deploy/job/request boundary, а не посреди единицы работы. Fleet-wide atomic cutover фреймворки не обещают; P11 его не заявляет. |
| P11-Q3 lifecycle reuse | reused | Топ-уровень уже подтвердил discover→validate→compile/cache и Shopware lifecycle. P11 разделяет code snapshot и tenant activation, а не вводит второй universal FSM. |

## Primary sources

- Symfony compiler passes: https://symfony.com/doc/current/service_container/compiler_passes.html
- Laravel 12 queues: https://laravel.com/docs/12.x/queues
- Laravel 12 Octane: https://laravel.com/docs/12.x/octane
- Reused top-level evidence: `research/perplexity-2026-09-19.md` Q13.

## Граница подтверждения

Внешние источники подтверждают lifecycle boundaries, но не определяют
CoreX identity, duplicate/replacement policy, canonical hash, tenant semantics и
durable mismatch outcome. Это проектные решения D56–D59. Perplexity
не дал прямого доказательства для fingerprint durable payload; эта
часть не маркируется как external guarantee.
