# P14 — RAG: cost, bulk, webhook security

2026-09-20; три Perplexity browser MCP запроса, пять direct primary-source reads.
Точные запросы/полные tool responses и SHA256 — artifacts/P14-design-rag/manifest.json.
Запросы нейтральные: внутренние пути и сведения CoreX не передавались.

| Evidence | Verdict / использование |
|:--|:--|
| Q1 budgets/LRO | Подтверждает patterns, а не CoreX defaults. Исправлена ошибочная предпосылка вопроса: AIP165 относится к purge, не общей отмене. Shopify cost accounting относится к GraphQL; переносится accounting pattern без GraphQL. |
| Q2 delivery identity | Partial: MCP возвратил только identity/dedup часть без источников. Остальные вопросы не считались подтверждёнными; добраны Q3 и прямым чтением. |
| Q3 signature/SSRF | Подтверждены точные signed bytes, base64 key serialization, multi-signatures и DNS threat. IP pinning — локальное решение угрозы, не выдуманное требование signing spec. |

## Проверенные первоисточники

- [Standard Webhooks specification](https://github.com/standard-webhooks/standard-webhooks/blob/main/spec/standard-webhooks.md): id стабилен на retry; attempt timestamp обновляется; HMAC над id.timestamp.raw_body; whsec_ base64; symmetric keys24–64bytes; signatures space-delimited; timestamp tolerance без обязательного числа; success2xx; 410 disable; retry-after, exponential jitter и manual replay. CoreX выбирает32bytes,300sec,12attempts/72h/7d самостоятельно. Standard не фиксирует manual replay id или fan-out identity: D80/D84 определяют их явно.
- [OWASP SSRF Prevention Cheat Sheet](https://github.com/OWASP/CheatSheetSeries/blob/master/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.md): отключение redirects, проверка всех A/AAAA и внутренних диапазонов; DNS rebinding остаётся угрозой при раздельных validate/connect. P14 pin-before-send и production/test separation — локальные controls. HTML endpoint403; raw Markdown owning repo200.
- [Shopify calculated query cost](https://shopify.dev/docs/apps/build/apis/graphql-admin/rate-limits): requested cost admission и requested-minus-actual refund. Иерархическая SQL atomicity, shared connection, conservative unknown handling и self-hosted defaults — D75, не заявление Shopify.
- [Google AIP151](https://google.aip.dev/151): durable operation содержит progress/metadata и execution result/error; expiry после completion допустим. Не доказывает rollback при cancel или переносимость конкретного retention. D77/D78 ограничивают гарантии по реальному transfer коду.

## Граница доказательств

Generic web tool отказал с connection failed; fallback direct HTTPS успешно получил
пять первоисточников. Их body hashes/размеры записаны, полные сайты не вендорятся.
Perplexity — retrieval/synthesis; нормативные PHP names, defaults, DDL, gate scopes,
CIDR policy и state algorithms остаются проектными решениями. Vendor лимиты/SLA не
перенесены. Local source reads подтвердили import checkpoint и export abort-unsealed.

Shopify обзорная limits страница перенесла cost детали на GraphQL Admin rate-limits;
переход проверен прямым чтением linked Markdown. Raw requested/actual/refund
подтверждены на новом URL, прежний обзор не выдаётся за источник формулы.
