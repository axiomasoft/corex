# P1 — дополнительная RAG/sequence проверка, 2026-09-15

## Findings

1. Major / high / issue: ранее Next рекомендовал P1.1 при десяти skeleton phases. После явного
   owner full-plan-first это запрещено; D4 и plan.md вводят общий design gate и заменяют Next.
2. Major / high / issue: successful Perplexity evidence отсутствует. До уточнения verify-claims
   требовал primary verification changing external premises и предпочитал Perplexity; текущий
   owner сделал Perplexity mandatory для каждой детализации. Эта новая обязанность для P1 pending.
   Query вызван через mcp__perplexity_web__search; tool вернул isError=true, login required. Не PASS.

## Дополнительные первоисточники

RAG:✅ 2026-09-15, direct web search/open fallback; **не successful Perplexity receipt**.

- [NASA SWE-052](https://swehb.nasa.gov/spaces/SWEHBVD/pages/102695427/SWE-052%2B-%2BBidirectional%2BTraceability):
  связь requirements→design→code→verification/non-conformances в обе стороны. Поддерживает
  сохранение parent criteria, атомарную классификацию, R/AC→item/evidence/destination tracing.
  NASA не назначает четыре пункта P1 и не обязывает CoreX принять aerospace lifecycle.
- [W3C PROV-DM, Recommendation 2013-04-30](https://www.w3.org/TR/prov-dm/):
  provenance описывает entities/activities/agents, derivation и responsibility; поддерживает
  отдельный учет source bytes, их происхождения, producer/owner и derived claims.
  Соответствие CoreX стандарту PROV не заявлено; JSON/RDF/PROV runtime dependency не вводится.

## Вывод и границы

Inference: два независимых источника поддерживают основные принципы P1 — lossless traceability
и provenance. Они не доказывают semantic completeness всех 365 критериев, оптимальность четырёх
items, правильность engine/API выбора или package/production acceptance. Саму P1 decomposition
проверяют repository constraints и observable deliverables. RAG review не аудит P2–P11.

Perplexity query: primary NASA SWE-052/W3C PROV-DM evidence for retained original criteria,
mixed decomposition, owners/bounded evidence/missing proof/downstream acceptance; scope and limits.
Observed response: login required; success evidence отсутствует. После авторизации сохранить
реальный response и primary verification в research/P1-design-rag.md; до этого D4 RAG gate pending.

## Gap closed — D5

Дополнительный запрос после автовхода успешен; NASA/W3C первоисточники сверены непосредственно.
Current mandatory RAG result — research/P1-design-rag.md с raw response/hash. Initial login-required
finding остаётся историческим observation; новых pending claims по этому scope нет. Общий execution
gate закрыт до P2–P11 detail/RAG и finish. Ничего из package/production acceptance этим не закрыто.
