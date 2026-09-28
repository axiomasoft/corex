---
id: D31
date: 2026-09-28
status: accepted
item: P00.1
items: [P00.1, P02, P06]
supersedes: []
superseded_by: null
---
# D31 — operations-split (accepted)

**Actor:** author-choice (claude-opus-5-5/high, Claude Code, 2026-09-28) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6); frontier-вердикт P00.1 в пределах `research/P00-design.md` §2.
**Evidence:** RAG:— `artifacts/P00-design/facts/{import-matrix.txt,zone-consumers.tsv}`, `packages/*/composer.json`, `artifacts/P00/*.tsv`; `check-maps.py` (default/`--dependencies`) exit 0 на `b624936`+P00.1.

## Решение

`packages/operations` делится по каталогам: `src/Tools/**` → `axiomasoft/corex-tools` (`CoreX\Tools\`), `src/Webhooks/**` (включая `Webhooks/{Commands,Laravel,Internal}`) → `axiomasoft/corex-webhooks` (`CoreX\Webhooks\`), остальное → `axiomasoft/corex-operations` (`CoreX\Operations\<путь>`). Тесты/фикстуры `Tool*`/`Webhook*` и миграция `create_sys_api_webhook_tables` — владельцам. Швы (все — P02 до механического split): (1) `ContractSnapshotBuilder`→`WebhookTopic` — invert: engine SPI `CoreX\Operations\OpenApi\Contracts\ContractSnapshotSection` (tag), webhooks вносит секцию topics; (2) `ApiServiceProvider`→`WebhookRegistrar/DeliverWebhooks/WebhookOptions` — remove: публичный `CoreX\Webhooks\WebhooksServiceProvider`; (3) `PruneApiOperations`→`WebhookRetention` — remove: `corex:webhooks:prune`; (4) `JsonCanonical`, `SchemaBudget`, `SchemaProfile` → `CoreX\Operations\Validation\`, `OrderingRegistry`, `SignedCursorCodec` → `CoreX\Operations\Pagination\` (публичны для tools/webhooks); (5) config `corex-api.webhooks` → `corex-webhooks.php`, route prefix webhooks читает публичный `ApiOptions` engine, не ключ `corex-api`. Тесты: `Tool*`/`Webhook*` — владельцам; `WebhookTransportTest` — два namespace (`curl_*`-шимы следуют `CurlWebhookTransport` в `CoreX\Webhooks\Internal`, класс теста — `CoreX\Webhooks\Tests\Feature`); смешанные тесты engine (`ApiRetentionTest`, `LoadContourIntegrationTest`, `AuthorizationLayersTest`, `CostInvocationTest`, `IdempotentInvocationTest`, `OperationParityTest`, `LoadContourBootTest`) делятся в P02 — пути tools/webhooks уходят владельцам (рёбра `remove`); общая поддержка engine — `CoreX\Operations\Testing\` (D35).

## Почему

- D12; F9: три независимых потребителя; 4–7 пакетов аудита отклонены.
- `dependency-edges.tsv`: после resolutions граф ацикличен (`check-maps.py --dependencies`); без них — `dependency-cycle` operations↔webhooks и `internal-edge` на `Internal` engine.
- Отклонено: оставить агрегацию snapshot в engine с импортом типов webhooks — цикл.

## Consequences

P02 реализует швы (1)–(5) и зелёные package gates до split; P06 — wire/стандарты, не развязка.
