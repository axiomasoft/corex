# PLAN3.P06.5 — Review P06 — 2026-10-04

verdict: GREEN
review: codex/GPT-6

Подтверждённых findings: 0. Проверены результат P06.1–P06.4, D90, уточнения D92 и принятый supplement D93. Продукт, тесты и нормативная документация не изменялись. RAG:—; новых внешних решений нет, использованы сохранённые первичные captures и исследование P06.

## Граница и provenance

- Base: `3f568ddd53520693cd94a7bc3761e9b5ac2412d0`, из completion P06.1.
- Candidate: `2a0265d57c93514f518ace028999debe52a4df2d`, из completion P06.4.
- Checkout при старте: `d4603a82`; между candidate и checkout продуктовых изменений нет.
- Прочитаны спеки P06.1–P06.4, completion каждого item, досье §4/§6, примеры, message policy и threat model. Рассмотрен сохранённый commit diff, включая объявленные локальные ремонты потребителей и BC-гейта.
- Владелец: «разрешаю исполнять sol, проверку игнорируй»; после уточнения границы P06.5 — «ну делай!!». Выполнено текущим Codex; проверка маршрута модели исключена по прямому указанию владельца. Selector/effort провайдера не аттестованы; исполнение Grok или дополнительное независимое ревью не заявляются.

## Проверенные пункты D90

| Пункт | Проверка и результат |
|:--|:--|
| 1 — HTTP errors | `ProblemDetailsSerializer.php:17` получает type/title из реального ProblemRegistry, detail из случая; path выводится только при наличии. Единственное представление problem+json; success data/meta. ErrorRepresentationTest проверяет Accept, все зарегистрированные определения, безопасный internal error и Retry-After. OpenAPI согласован с новым wire; удалённые negotiation/config поверхности не имеют runtime-мостов. |
| 2 — InputEnvelope | `OperationFactory.php:42` выводит enum из метода; readers проверяют форму, `OperationDispatcher.php:98` всегда проверяет необъявленные поля до исполнения. Три причины различимы; fields отсортированы. InputEnvelopeTest проверяет обе политики, порядок query/media/JSON/object и отсутствие effects при отказе. |
| 3 — Tools | Три enum outcome и 13 кодов по D93; ToolSchemaFailure хранит отдельное errorCode. GuardedToolInvoker разделяет typed denials и проброс зарегистрированных operations codes; MCP сохраняет wire outcome. Регистрация definitions происходит в register до freeze. Guard/effect/approval/idempotency/Unknown сохраняют исходную семантику. |
| 4 — CloudEvents | `JsonCloudEventCodec.php:30/:69/:78`: байтовый эталон, UTC microseconds, канонический scope, закрытая metadata, строгие decode/URI cases. Внешний профиль содержит ровно семь объявленных атрибутов; redacting projector не переносит secret, schema, scope, subject или trace. Кодек scoped; новых зависимостей нет. |
| 5 — Webhooks | `WebhookFanout.php:63` сохраняет внешние CloudEvent bytes/hash; signer/transport используют сохранённое тело. Content-Type обновлён, Standard Webhooks headers/algorithm сохранены. Двадцать причин, общий retry/recovery budget, typed SQL boundaries. Egress проверяется на create/update и каждой попытке, retry/replay до DNS/transport. Threat model T1–T10 связан с существующими тестами. |

## Проверенные пункты D92

| Пункт | Проверка и результат |
|:--|:--|
| 1 — 415 | Реальное определение и occurrence используют Validation/415; проверка media-type через зарегистрированный сериализатор зелёная. |
| 2 — Schema failure | ToolSchemaFailure::$errorCode отделён от Exception::$code; invoker ловит typed failure, plain RuntimeException даёт tools.internal_error. Taxonomy tests зелёные. |
| 3 — External schema | encodeExternal не выводит dataschema даже при внутренней schema; fanout redaction проверяет отсутствие secret и false schema claim. |
| 4 — URI grammar | Один UriReference validator для source/dataSchema; percent escapes, relative first segment и IPv6/IPvFuture проверены положительными/отрицательными codec cases. Schema требует scheme и запрещает fragment. |
| 5 — Shared budget | budgetFailure вызывается в decide и recoverExpired; equality на horizon исчерпывает бюджет, attempts имеют приоритет. Success/permanent classification сохраняется. Recovery записывает attempt unknown/ack_unknown отдельно от delivery exhaustion; fences сохранены. |
| 6 — Taxonomy/persistence | Все 20 причин round-trip; HTTP producer/consumer используют enum, SQL пишет value. EndpointChanged permanent, AckUnknown transient. Bad-signature fixture 401/null и load-contour assertions сохранены. Дополнительные регрессии P06.4 сохраняют transport refusal при HTTP 2xx/410/429/503 и не скрывают ошибку сохранения egress denial. |
| 7 — Review inputs | Оба SHA существуют; 124 изменённых repo paths рассмотрены с разделением нормативного scope и объявленных ремонтов. Текущие байты всех существующих изменённых файлов совпадают с candidate, P06.4 product-manifest совпадает. Исходная грязь сохранена. |
| 8 — Contract guard | В продуктовых commits P06.1–P06.4 manifests и package changelogs изменены вместе. Прочитаны completion и сохранённые committed guard results; новый продуктовый delta ревью не создаёт. |

Golden: фактическая суммарная дельта соответствует четырём поимённым execution inventories и досье §4 с D92/D93. PHP surface: 16 добавлений, 2 удаления, 16 изменённых записей; events меняет только message_contracts, wire — только errors. Routes byte-identical базе. Важные дополнения к первоначальному runbook (root error fixtures, canonical DataScope selectors, exact BC allowance и mutation reanchor) объявлены и подтверждены predecessor evidence.

## Validation текущего ревью

PHP 8.4.26, полный php84 extension preflight. PostgreSQL: healthy compose pgsql, host `127.0.0.1:5434`, проверенная база `corex_test`; fanout PG test создаёт/удаляет только свой test schema и применяет миграции fixture. Остальные выбранные cases используют SQLite memory.

| Команда | Exit | Наблюдаемый результат |
|:--|:--|:--|
| `scripts/php84 composer --working-dir=packages/operations test -- --filter='ErrorRepresentation\|InputEnvelope'` | 0 | 22 passed, 1155 assertions |
| `scripts/php84 composer --working-dir=packages/tools test -- --filter=ToolErrorTaxonomy` | 0 | 15 passed, 130 assertions |
| `scripts/php84 composer --working-dir=packages/core test -- --filter=CloudEventCodec` | 0 | 58 passed, 64 assertions |
| `PGSQL_DATABASE=corex_test PGSQL_PORT=5434 scripts/php84 composer --working-dir=packages/webhooks test -- --filter='WebhookFanout\|WebhookFailureReason\|WebhookRetryBudget\|WebhookEgressPolicy'` | 0 | 44 passed, 309 assertions; PG fanout включён, skipped нет |
| `scripts/php84 composer error-registry` | 0 | diagnostics: [] |
| `scripts/php84 composer bc-check` | 0 | bc-check: OK; 20 пакетов: 17 compatible, 3 exact decision-bound unpublished deltas |

Итого 139 tests / 1658 assertions. BC сравнивает accepted base `76f8bdde496154c0a8901e459175cf306bedd860`; operations/tools/webhooks дают ровно объявленные D90/D92/D93-bound наборы, без новой allowance или изменения base. Это полный завершённый BC прогон, не частичный лог и не reuse предыдущего прогона.

SHA-256 сырых текущих логов (рабочие логи `/tmp/corex-p065-review/`; команды и итог выше сохраняются в этом единственном review artifact):

```text
operations 8a7f4ee6eb68451f5039c1597e2c73c558d078a1437fba25beb2079744d1f427
tools      5f641a04059f756d911b322bf99dacb14df0e40f7614cffe7fcb347dff5ff0f7
core       a27e035994a385e9246b98f330125890f83d798e9638e214175f6782cef0c03d
webhooks   a3e419de9986072e8e1b565699203e69c43494852b77268971210f74e94dbed4
registry   e25936bf0539bb8cc05d4b8f9c1f874e11454343d43a5049c088f984b1250c72
bc         2ae86e869b7300e1bc62db42480c04e6c997a77cc769e9c9c49296612eb2eeca
```

Негативные контроли прочитаны из сохранённых proof: P06.1 `negative-title.log` — 9 failed под message-derived title, restored/current GREEN; P06.2 `negative-denied.json` — PHPStan exit 1 под string denial, restored exit 0; P06.4 `mutation-egress.log` — оба revocation cases красные под удалением enforcement. Патч удаляет именно вызов egress в текущем worker; текущие оба cases зелёные. Во время read-only ревью патчи не накладывались.

Read-only proof: перед стартом сохранены git porcelain и SHA-256 9708 tracked/untracked owner files. После Validation изменений продукта и исходной посторонней грязи нет. Единственный новый review artifact — этот файл; owning root обновляет journal/state/status/handoff через Task. Нормативные спеки и решения не меняются. После terminal item roll-up plan-lint потребовал непустой Phase Handoff; owning root заполнил только этот служебный раздел P06.md, сохранив в нём незакрытую границу hosted CI и будущего ревью.

## Не проверено

- Hosted CI финального commit фазы и закрытие P06: исключены из P06.5 по Scope Excluded и D91 п.4. Verdict GREEN не заменяет эту приёмку.
- Запланированное владельцем последующее полное ревью фазы; дополнительный reviewer в этой invocation не запускался.
- Live Internet delivery, host-specific egress implementations, fleet и production deployment. Их успешность не выводится из этих тестов.
- Broad suites и новые mutation executions здесь не повторялись: прочитаны predecessor proofs, выполнены ровно обязательные behavioral commands P06.5.
- Provider model/effort attestation: исключена владельцем. Не заявляется доказательство исходного frontier/high маршрута.

## Root closure boundary

P06.5 завершается как read-only review item. По его Scope Excluded и D91 п.4 owning root сохраняет отдельную границу hosted-CI/phase close; phase closure/audit не выдаётся этой invocation. Служебная финализация не разрешает ни продуктовый ремонт, ни запуск следующего scope.

Состояние item — Done with deviations: единственное отклонение — разрешённый владельцем Sol и исключённая проверка исходного frontier/high provider route. Технический verdict и все Validation GREEN. Отсутствующее исходное route proof не представлено как успешная аттестация; дальнейшее полное ревью фазы остаётся выбранным владельцем продолжением.

Root reconciliation выполнена Task `plan-work finalize`; terminal-delivery receipt построен из journal/state и точного manual Next. `plan-lint --baseline HEAD --gate new`: 0 errors, 0 new; единственный inherited warning относится к workflow P04. `git diff --check` на затронутых carriers прошёл. Phase Handoff сообщает оставшуюся границу приёмки; phase closure не создавалась.

write-site: pre_mutation: allow · pre_final: allow

Root checks use the original Task predicate with only model/effort rank verification excluded by the explicit owner instruction. The actual route remains unknown; no provider proof was manufactured. Native pre_final validated receipts E13–E16 against the immutable spec and current context.

```json
{
  "contract_sha256": "178d1fd708e6b2d253f4cfbce593c8b681f163995d9299c62c45b724f0acc342",
  "spec_sha256": "ab84d1ad0bced89ea52c2e0ca1c534be9b2b13c4b27828787d2548e551e40ae6",
  "validation_head": "d4603a8248dc287dd9a251c2fb667e26bea267a7",
  "validation_tree": "329cac47a493aaec2803281894f54281adc275f3af2d02628d7b802b8b52dd41",
  "receipt_ids": [
    "E13",
    "E14",
    "E15",
    "E16"
  ],
  "receipt_exits": [
    0,
    0,
    0,
    0
  ],
  "route_check": "owner-waived"
}
```
