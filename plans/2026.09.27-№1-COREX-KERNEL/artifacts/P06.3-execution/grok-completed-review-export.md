## User

Independent read-only review by owner-requested Grok 4.7. Reasoning effort medium is appropriate for this frozen implementation with green acceptance. Review ALL included actual source bytes and exact diff. No tools/file access/commands/edits are allowed or needed. Return explicit Verdict: GREEN or RED with concrete defects and source locations; no style nits. The prior 10-minute request produced no verdict or usage before it was interrupted; this compact packet omits large generated API JSON diffs and repeated changelog text. Native hooks stay unchanged. This is the same authorized item, not a new design/audit.
Independent read-only code review, explicitly requested by owner on Grok 4.7 High. All necessary evidence is embedded below. Do not invoke tools, search, access files, edit, spawn, mutate plan/memory or run commands: this is a complete immutable source packet, avoiding a broken local PreToolUse compatibility hook that denied every repository read. Do NOT treat that procedural hook failure as a product defect. Review the actual included bytes. Return explicit Verdict: GREEN or RED and only actionable material defects with file/line, trigger and impact. No style-only suggestions. Existing accepted design D90/D92 below is authoritative; item scope P06.3 only.

Check byte-exact CloudEvents key order, UTC time, internal round-trip, strict decode, RFC3986 URI grammar/source/schema checks, external redaction and absence of dataschema/internal attrs, registry/DI, byte-preserving webhook signature/body-size behavior, public data projector migration, tests and docs. Current initial candidate is 1c3de3bd and following working diff repairs it; final SHA will be bound to byte-identical reviewed files. Typed API gate rejects the designed array<string,mixed>; parameter native array is unchanged and PHPDoc is refined to a concrete eight-level JSON tree matching existing payload limits, with floats for JSON numbers. Root suite's stale fixture omitted 3 operations+13 tools definitions from P06.1/P06.2; only definitions were synchronized, existing rows and all other sections retained, with no error-product changes in this item. Check these bounded repairs as part of candidate. No new phase outcome or validation floor is introduced.

Validation so far: core300, focused73, webhooks102 incl6PG, prior root PG242, Golden4, strict PHP API0, full BC cohort0, antiweakening0, current scoped-root repair27+17. Final root suite/analyse/lint are running; no final success claim for pending checks. The packet's current product-source hashes, not my summary, are authoritative. You can issue a static code verdict while root executor completes acceptance independently.

FILE plans/2026.09.27-№1-COREX-KERNEL/phases/P06/P06.3.md
### P06.3 — Конверт CloudEvents для сообщений и тела вебхуков

**Intent:** Дать опубликованному сообщению стандартный wire — CloudEvents 1.0 structured JSON через публичный кодек с внутренним и внешним профилями — и перевести тело вебхука на внешний профиль при неизменной подписи Standard Webhooks.

**Why:** D90 п.4–п.5; D11 (CloudEvents для outbox, breaking → новый `vN` + `dataschema`); MSG-003 объявляет конверт без стандарта, а wire существует только как docblock `MessagePublisher` (`artifacts/P06-design/facts/message-wire.txt`). Внешнему получателю нельзя отдавать scope, trace и subject (`research/P06-design-rag.md` W12). Первичные правила CloudEvents — W5–W9.

**Scope Included:** `CoreX\Messaging\Contracts\CloudEventCodec` (`#[Api]`) и `CoreX\Messaging\Internal\JsonCloudEventCodec` с регистрацией в `MessagingRegistrar`; проверка URI-reference для `MessageEnvelope::$source`; `MessageEnvelope::withPayload()`; `MessageContract::$dataSchema`; docblock `MessagePublisher`; `WebhookPayloadProjector::project()` возвращает объект данных; `WebhookFanout` кодирует тело `encodeExternal()`; `CurlWebhookTransport` — `Content-Type: application/cloudevents+json`; правка реализаций projector в тестах и продукте; `CloudEventCodecTest`, правка `WebhookFanoutTest`; `docs/architecture/message-policy.md` (раздел конверта), `constitution.md` MSG-003, `references.md`; Golden delta P06.3.

**Scope Excluded:** Транспортная реализация `MessagePublisher` (принадлежит хосту); CloudEvents binary mode и batch; `WebHook-Request-Origin` handshake; заголовки и алгоритм подписи вебхука; схема таблиц outbox/вебхуков (P08); причины отказа и egress (P06.4); `cloudevents/sdk-php`.

**Inputs:** `plans/2026.09.27-№1-COREX-KERNEL/decisions/D92-p06-design-audit-remediation.md` · `plans/2026.09.27-№1-COREX-KERNEL/research/P06-design.md` (§1, §3.3, §4, §5) · `plans/2026.09.27-№1-COREX-KERNEL/research/P06-design-rag.md` (W5–W10, VERIFY-AT-EXEC) · `plans/2026.09.27-№1-COREX-KERNEL/artifacts/P06-design/examples.md` (§6) · `plans/2026.09.27-№1-COREX-KERNEL/decisions/D90-p06-wire-contract.md` · `plans/2026.09.27-№1-COREX-KERNEL/artifacts/P06-design-rag/http-01-cloudevents-spec.md` · результат P06.2 (`plans/2026.09.27-№1-COREX-KERNEL/artifacts/P06.2-execution/completion.md`, появляется после его исполнения)

**Files:** `packages/core/CHANGELOG.md`, `packages/webhooks/CHANGELOG.md`; `packages/core/src/Messaging/Contracts/CloudEventCodec.php`, `packages/core/src/Messaging/Internal/JsonCloudEventCodec.php`, `packages/core/src/Messaging/Internal/UriReference.php` (новые); `packages/core/src/Messaging/Data/{MessageEnvelope,MessageContract}.php`; `packages/core/src/Messaging/Contracts/MessagePublisher.php`; `packages/core/src/Messaging/Laravel/MessagingRegistrar.php`; `packages/core/tests/Unit/CloudEventCodecTest.php` (новый), `packages/core/tests/Unit/MessagingEnvelopeTest.php`; `packages/webhooks/src/Contracts/WebhookPayloadProjector.php`; `packages/webhooks/src/Internal/{WebhookFanout,CurlWebhookTransport}.php`; `packages/webhooks/src/Laravel/WebhookBindingsRegistrar.php` (инъекция кодека); реализации `WebhookPayloadProjector` в `packages/*/tests/**` и `products/**`; `packages/webhooks/tests/Feature/**` (`WebhookFanoutTest`); `docs/architecture/{message-policy,constitution,references}.md`; `packages/{core,webhooks}/resources/contracts/php-api.json`; `tests/Golden/baseline/*` (строки P06.3 из §4); `plans/2026.09.27-№1-COREX-KERNEL/artifacts/P06.3-execution/**`.

**Required Reads:** 1) `plans/2026.09.27-№1-COREX-KERNEL/research/P06-design.md` §3.3, §4; 2) `plans/2026.09.27-№1-COREX-KERNEL/artifacts/P06-design/examples.md` §6; 3) `plans/2026.09.27-№1-COREX-KERNEL/research/P06-design-rag.md` (W5–W10, VERIFY-AT-EXEC); 4) `packages/core/src/Messaging/Data/MessageEnvelope.php`; 5) `packages/core/src/Messaging/Data/MessageContract.php`; 6) `packages/webhooks/src/Internal/WebhookFanout.php`; 7) `packages/webhooks/src/Internal/CurlWebhookTransport.php` (стр. 120–160); 8) `docs/architecture/message-policy.md` (раздел «Конверт опубликованного сообщения»).

**Implementation Rules:** D92 — обязательное уточнение D90. Manifest и changelog меняются вместе в одном продуктовом commit; исходный SHA сохраняется до правок, candidate SHA — в completion (§4 досье). Порядок ключей и формат `time` — ровно examples §6; отсутствующие необязательные атрибуты не выводятся. Extensions — только скаляры; scope — одна каноническая JSON-строка с рекурсивно отсортированными ключами. Метаданные — закрытый набор `traceparent`/`tracestate`. `decode` строгий (examples §6b). Внешний профиль без `dataschema`, `corex*`, `subject`, trace даже при непустом внутреннем dataSchema: вывод проектора может не отвечать внутренней схеме (D92). source и dataSchema проверяет одна полная грамматика RFC 3986 (examples §6c), не allow-character regex. Подпись вебхука считается по сохранённым байтам тела; тело не пере-сериализуется между fan-out и отправкой. Значение `source`, нарушающее правило, исправляется у производителя, а не ослаблением проверки. Новых зависимостей нет.

**Code Guidance:** Досье §3.3 шаги 1–9; examples §6 (интерфейс, worked example байт-в-байт, внешний профиль, негативы и round-trip). Поиск реализаций projector: `git grep -n "implements WebhookPayloadProjector" packages products`.

**Validation:**
- После продуктового commit: `bash scripts/contract-guard.sh` и `bash scripts/contract-guard.sh "$(cat plans/2026.09.27-№1-COREX-KERNEL/artifacts/P06.3-execution/base-commit.txt)"` → exit 0; в Unreleased каждого изменённого пакета описан объявленный API/wire delta.
- VERIFY-AT-EXEC из `research/P06-design-rag.md` → оба ожидания выполнены (запись в Completion Notes).
- `scripts/php84 composer --working-dir=packages/core test -- --filter='CloudEventCodec|MessagingEnvelope'` → exit 0: эталон examples §6 байт-в-байт, round-trip, шесть негативов decode, encode-негативы и URI cases §6c (валидные relative/absolute, malformed percent/scheme/authority/IP-literal); внешний профиль без `dataschema`/`corex*`/`subject`/trace, в том числе после редактирующего проектора при заданной внутренней схеме.
- `scripts/php84 composer --working-dir=packages/webhooks test -- --filter=WebhookFanout` → exit 0: тело разбирается `decode`-совместимым JSON с `specversion` `"1.0"` и `data` = вывод projector; подпись проверяется по тем же байтам; projector удаляет required secret внутренней schema, внешний event не объявляет dataschema и не содержит secret.
- `git grep -n "двенадцать ключей" packages docs` → пусто; `grep -n "Content-Type: application/cloudevents+json" packages/webhooks/src/Internal/CurlWebhookTransport.php` → одна строка.
- `scripts/php84 composer --working-dir=packages/core test`, `scripts/php84 composer --working-dir=packages/webhooks test` → exit 0; `PGSQL_PORT=5434 scripts/php84 composer test:pg` → exit 0.
- `php scripts/package-docs-gate.php` → exit 0; `grep -n "CloudEvents" docs/architecture/message-policy.md docs/architecture/constitution.md` → строки в обоих файлах.
- `scripts/php84 php scripts/php-api.php --write` дважды → идентично; `scripts/php84 composer php-api`, `scripts/php84 composer bc-check` → exit 0; diff baseline Golden содержит только строки P06.3 из досье §4.
- `scripts/php84 composer lint:check`, `scripts/php84 composer analyse` → exit 0; `bash scripts/anti-weakening-gate.sh` → exit 0.
- Review `light` (D91): self-check по досье §6; строка `review: self-check GREEN` в Completion Notes.

**Deliverables:** Package changelog(s), `artifacts/P06.3-execution/base-commit.txt` и точный candidate SHA в completion; `CloudEventCodec` и `JsonCloudEventCodec`; `MessageContract::$dataSchema`, проверка `source`, `withPayload`; тело вебхука CloudEvents; `CloudEventCodecTest`; обновлённые `message-policy.md`, MSG-003, `references.md`; Golden delta P06.3; `artifacts/P06.3-execution/completion.md`.


FILE plans/2026.09.27-№1-COREX-KERNEL/decisions/D92-p06-design-audit-remediation.md
---
id: D92
date: 2026-10-03
status: accepted
item: P06.1
items: [P06.1, P06.2, P06.3, P06.4, P06.5]
supersedes: []
superseded_by: null
---
# D92 — p06-design-audit-remediation (accepted)

**Actor:** designer/codex/gpt-6.1-sol/xhigh, owner-authorized correction: «правь все» (2026-10-03). Current route recaptured from provider-thread-state with additive-owner-followup binding to the last explicit design-audit invocation (`artifacts/P06-design-remediation/route-evidence.json`). This continues the existing root; no fresh design launch or product admission is claimed. Recommended design effort high; inherited actual xhigh is visible here rather than relaunching another model for this bounded correction.
**Evidence:** RAG:— `findings/P06-design-audit.md` A1–A6, m1–m2; independent report and reproductions in `artifacts/P06-design-audit/`; contract guard `scripts/contract-guard.sh`; current classes at product baseline `3f568ddd`. RAG:✅ RFC 3986 grammar verified in `artifacts/P06-design-audit/rfc3986-source-verification.md`; CloudEvents optional `dataschema`, retained W5–W9 sources in `research/P06-design-rag.md`.

## Solution

This is a scoped amendment of D90, with priority over its p.2/p.4/p.5 wherever the statements below differ. All other D90 rules and D91 routing stay in force. Historical audit evidence is preserved.

1. **415:** `operations.unsupported_media_type` uses `ErrorCategory::Validation` in both definition and occurrence, consistent with `CompiledProblemRegistry` and `ApiError`. HTTP status remains 415. Core category rules do not change. Acceptance includes booting the registered definition and serializing a non-JSON request.
2. **Schema failure:** Internal `ToolSchemaFailure extends RuntimeException` carries `public readonly ToolsErrorCode $errorCode`; `catch` reads `$e->errorCode`. Inherited `Exception::$code` is left alone.
3. **External schema:** `encodeExternal` always emits exactly `specversion,id,source,type,time,datacontenttype,data`. It omits `dataschema`, including when the internal contract has one: the projector may redact required internal fields. Internal `encode` keeps the registered `dataschema`. No new external-schema SPI is added. A redacting projector test proves the emitted data is safe and makes no false internal-schema claim.
4. **URI:** source is a nonempty RFC 3986 URI-reference ≤128 bytes. `MessageContract::$dataSchema` is a valid absolute URI (scheme required, no fragment), not merely a string with a scheme-like prefix. Both use one internal grammar validator, with escaped percent sequences, scheme/relative first-segment rules and IP-literal syntax. Full grammar and positive/negative cases are in examples §6c. No SDK or dependency is added.
5. **Budget:** normal retry and lost-lease recovery use the same `WebhookOptions` and pure `WebhookRetryPolicy::budgetFailure(delivery, instant): ?WebhookFailureReason`. Attempts `>= maxAttempts` take precedence; otherwise `instant >= createdAt + retryHorizonSeconds` exhausts the horizon. A retry scheduled exactly at the horizon is dead. Normal successful/permanent outcomes retain classification precedence; claim rejection preserves its existing dead status. Recovery keeps attempt `outcome=unknown` and reason `ack_unknown`, while delivery `last_error` uses the actual exhaustion reason or `ack_unknown`. Existing lease-owner/attempt fencing and replay reset remain intact.
6. **Taxonomy:** twenty cases, adding permanent `EndpointChanged='webhook_endpoint_changed'` and transient `AckUnknown='ack_unknown'` to D90's eighteen. Internal retry decisions and public `WebhookHttpResult::$errorCode` carry `?WebhookFailureReason`. Worker, Curl transport, fanout, store recovery/rejection/finish and tests are migrated together; at every persisted `last_error`/`error_code` boundary write `?->value`. Private store helpers take enums, never pass an enum directly to SQL or a `?string` argument. Endpoint/signing rejection stays dead as before; signing cause remains transient for normal retry. The receiver-only fixture `tests/Fixtures/LocalWebhookReceiver.php` returns HTTP 401 with `errorCode:null` for bad signatures; update the load-contour assertion to 401/null while retaining rejection/effects checks. The delivery-test-only `network_timeout` maps to `NetworkFailure`, without adding a transport case. The DTO type change is an explicit pre-1.0 public delta under D01/D64, with manifest and changelog.
7. **Review inputs:** fully qualified brace-expanded item paths and predecessor completion paths. Diff is computed at review from exact base/candidate SHAs recorded in completions; it is an operation, not a file declared in Required Reads. Reviewer compares initial/final product state and preserves preexisting unrelated changes.
8. **Contract guard:** each item changing a package's API manifest also writes that package's `CHANGELOG.md` (Unreleased, concrete public/wire delta) and runs `bash scripts/contract-guard.sh` on the product candidate commit containing both changes. Capture the pre-item commit in completion evidence and use that exact base for the full item diff as described in dossier §4; no dirty-only or HEAD-only substitute.

## Why

A1 and m1 are executable PHP/registry defects. A2/A3 omit live recovery and persistence seams. A4 advertises a schema that redacted data cannot satisfy. A5 fails cold bundle construction; A6 fails the adopted release gate. m2 only checks characters, so malformed URI references pass. Corrections use existing package boundaries and make every new contract and acceptance delta explicit.

## Consequences

P06.1–P06.5, dossier/examples and phase context include this amendment. D90's external attribute list, eighteen-case taxonomy and the old examples are no longer the current contract at these points. `WebhookHttpResult::$errorCode` is included in the P06.4 Golden/BC delta. Product implementation remains unstarted. Finish produces a new fingerprint; only an independent GREEN design audit of that fingerprint opens execution.


FILE plans/2026.09.27-№1-COREX-KERNEL/artifacts/P06-design/examples.md
## 6. CloudEvents (P06.3)

```php
#[Api(since: '1.0')]
interface CloudEventCodec
{
    /** Internal transport: full CloudEvents 1.0 structured JSON with corex extensions. */
    public function encode(MessageEnvelope $message): string;

    /** External receiver: core attributes only; `data` replaces the payload. @param array<string, mixed> $data */
    public function encodeExternal(MessageEnvelope $message, array $data): string;

    public function decode(string $json): MessageEnvelope;
}
```

Worked example. Вход:

```php
new MessageEnvelope(
    messageId: '01J9Z6Q4A7', source: 'axiomasoft/corex-custom-fields', name: 'customfields.attribute.changed', contractVersion: 1,
    occurredAt: new DateTimeImmutable('2026-10-03T08:00:00.123456Z'), scope: ['workspace' => 'w-7', 'account' => 'a-1'],
    payload: new MessagePayload(['attribute' => 'color']), piiClass: PiiClass::None, subject: 'attr-42',
    correlationId: 'c-1', metadata: ['traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'],
);
// контракт зарегистрирован с dataSchema: 'https://schemas.example.test/customfields.attribute.changed/v1.json'
```

`encode()` — байт-в-байт (одна строка; здесь перенесено для чтения):

```json
{"specversion":"1.0","id":"01J9Z6Q4A7","source":"axiomasoft/corex-custom-fields","type":"customfields.attribute.changed.v1",
"subject":"attr-42","time":"2026-10-03T08:00:00.123456Z","datacontenttype":"application/json",
"dataschema":"https://schemas.example.test/customfields.attribute.changed/v1.json",
"corexscope":"{\"account\":\"a-1\",\"workspace\":\"w-7\"}","corexpii":"none","corexenvelope":1,"corexcorrelation":"c-1",
"traceparent":"00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01","data":{"attribute":"color"}}
```

Отсутствующие необязательные атрибуты (`subject`, `dataschema`, `corexcorrelation`, `corexcausation`, `traceparent`, `tracestate`) не выводятся, `null` не пишется.
Значение `corexpii` — `PiiClass::value` (`none`, `pseudonymous`, `personal`).

`encodeExternal($message, ['attribute' => 'color'])`:

```json
{"specversion":"1.0","id":"01J9Z6Q4A7","source":"axiomasoft/corex-custom-fields","type":"customfields.attribute.changed.v1",
"time":"2026-10-03T08:00:00.123456Z","datacontenttype":"application/json",
"data":{"attribute":"color"}}
```

b) `CloudEventCodecTest` — негативы `decode` (каждый → `InvalidMessage`): `specversion` `"0.3"`; лишний атрибут `"foo":"x"`; `data_base64`; `type` `"customfields.attribute.changed"` без `.vN`; `time` без зоны; `corexscope` не JSON-объект. Encode-негативы: метаданные `{"x-tenant":"a"}` → `InvalidMessage`; `source` `"has space"` → `InvalidMessage` в конструкторе конверта.
Round-trip: `decode(encode($m)) == $m` для примера выше и для конверта без необязательных полей.

c) URI-reference validator (D92). `packages/core/src/Messaging/Internal/UriReference.php` below is a worked skeleton; add the namespace and normal style/imports at execution. Grammar: [RFC 3986 Appendix A](https://www.rfc-editor.org/rfc/rfc3986.html#appendix-A). It validates generic syntax, not scheme-specific network policy. The grammar permits an empty reference, but `MessageEnvelope` keeps its existing nonempty/≤128 source checks. `dataSchema` uses `isAbsolute` (scheme required, no fragment), per the absolute-URI production.

```php
<?php

declare(strict_types=1);

/** @internal RFC 3986 Appendix A generic syntax, not network reachability or scheme policy. */
final class UriReference
{
    private const string Pattern = <<<'REGEX'
~\A(?:(?&uri)|(?&relative))(?:\?(?&query))?(?:\#(?&query))?\z
(?(DEFINE)
    (?<unreserved> [A-Za-z0-9._\~-] )
    (?<subdelims> [!$&'()*+,;=] )
    (?<pct> %[0-9A-Fa-f]{2} )
    (?<pchar> (?&unreserved)|(?&pct)|(?&subdelims)|[:@] )
    (?<segment> (?&pchar)* )
    (?<segmentnz> (?&pchar)+ )
    (?<segmentnc> (?:(?&unreserved)|(?&pct)|(?&subdelims)|@)+ )
    (?<abempty> (?:/(?&segment))* )
    (?<absolute> /(?:(?&segmentnz)(?:/(?&segment))*)? )
    (?<rootless> (?&segmentnz)(?:/(?&segment))* )
    (?<noscheme> (?&segmentnc)(?:/(?&segment))* )
    (?<scheme> [A-Za-z][A-Za-z0-9+.-]* )
    (?<userinfo> (?:(?&unreserved)|(?&pct)|(?&subdelims)|:)* )
    (?<regname> (?:(?&unreserved)|(?&pct)|(?&subdelims))* )
    (?<octet> 25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9][0-9]|[0-9] )
    (?<ipv4> (?&octet)\.(?&octet)\.(?&octet)\.(?&octet) )
    (?<h16> [0-9A-Fa-f]{1,4} )
    (?<ls32> (?&h16):(?&h16)|(?&ipv4) )
    (?<ipv6>
        (?:(?&h16):){6}(?&ls32)
      | ::(?:(?&h16):){5}(?&ls32)
      | (?&h16)?::(?:(?&h16):){4}(?&ls32)
      | (?:(?:(?&h16):){0,1}(?&h16))?::(?:(?&h16):){3}(?&ls32)
      | (?:(?:(?&h16):){0,2}(?&h16))?::(?:(?&h16):){2}(?&ls32)
      | (?:(?:(?&h16):){0,3}(?&h16))?::(?&h16):(?&ls32)
      | (?:(?:(?&h16):){0,4}(?&h16))?::(?&ls32)
      | (?:(?:(?&h16):){0,5}(?&h16))?::(?&h16)
      | (?:(?:(?&h16):){0,6}(?&h16))?::
    )
    (?<ipfuture> [vV][0-9A-Fa-f]+\.(?:(?&unreserved)|(?&subdelims)|:)+ )
    (?<host> \[(?:(?&ipv6)|(?&ipfuture))\]|(?&regname) )
    (?<authority> (?:(?&userinfo)@)?(?&host)(?::[0-9]*)? )
    (?<hier> //(?&authority)(?&abempty)|(?&absolute)|(?&rootless)| )
    (?<relative> //(?&authority)(?&abempty)|(?&absolute)|(?&noscheme)| )
    (?<uri> (?&scheme):(?&hier) )
    (?<query> (?:(?&pchar)|[/?])* )
)
~xD
REGEX;

    public static function isValid(string $value): bool
    {
        return preg_match(self::Pattern, $value) === 1;
    }

    public static function isAbsolute(string $value): bool
    {
        return self::isValid($value)
            && preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:/D', $value) === 1
            && ! str_contains($value, '#');
    }
}
```

Positive source cases: `corex/relay`, `axiomasoft/corex-custom-fields`, `fixture-address/1`, `./1:foo`, `/a/b`, `../a`, `?q=a%20b`, `#fragment`, `urn:corex:source`, `https://example.test/a%20b?q=x#f`, `https://[::1]/`, `https://[2001:db8::1]:443/a`, `https://[::ffff:192.0.2.1]/`, `https://[v1.a:b]/`.
Negative cases (constructor → `InvalidMessage`): empty/129-byte source; space/control/Unicode; `%`, `%zz`, `a%2`, `1:foo`, `http://[not-ip]/`, `https://[::1`, `https://[1::2::3]/`, `https://host:port/`, `https://host/a[b]`, `https://host/#a#b`.
`dataSchema`: accept `urn:corex:schema:v1`, `https://schemas.example.test/v1.json`; reject relative path, malformed percent/IP-literal and fragment-bearing URI. Source URI-reference permits a fragment; dataSchema absolute-URI does not.

The external profile always omits dataschema. Redaction test fixture: internal schema requires `{id, secret}`, internal data supplies both; projector returns `{id}`. Internal encode retains the schema; external encode contains `{id}` only and has no dataschema, so it never claims the redacted object satisfies that internal schema.



FINAL COMMITTED CANDIDATE bf557c36ca9cdcec93930e22f71fb22b6a3c8674..5fff36ed801c0f42f803cea85ebdc3fe33a2ddf8
diff --git a/packages/core/src/Messaging/Contracts/CloudEventCodec.php b/packages/core/src/Messaging/Contracts/CloudEventCodec.php
new file mode 100644
index 00000000..012fe896
--- /dev/null
+++ b/packages/core/src/Messaging/Contracts/CloudEventCodec.php
@@ -0,0 +1,34 @@
+<?php
+
+declare(strict_types=1);
+
+namespace CoreX\Messaging\Contracts;
+
+use CoreX\Attributes\Api;
+use CoreX\Messaging\Data\MessageEnvelope;
+
+/**
+ * CloudEvents 1.0 structured JSON with internal and external profiles.
+ * Projected data uses the same eight-level JSON tree boundary as message payloads, including JSON numbers.
+ *
+ * @phpstan-type EventLevel0 null|bool|int|float|string
+ * @phpstan-type EventLevel1 null|bool|int|float|string|array<array-key, EventLevel0>
+ * @phpstan-type EventLevel2 null|bool|int|float|string|array<array-key, EventLevel1>
+ * @phpstan-type EventLevel3 null|bool|int|float|string|array<array-key, EventLevel2>
+ * @phpstan-type EventLevel4 null|bool|int|float|string|array<array-key, EventLevel3>
+ * @phpstan-type EventLevel5 null|bool|int|float|string|array<array-key, EventLevel4>
+ * @phpstan-type EventLevel6 null|bool|int|float|string|array<array-key, EventLevel5>
+ * @phpstan-type EventLevel7 null|bool|int|float|string|array<array-key, EventLevel6>
+ * @phpstan-type EventData array<string, EventLevel7>
+ */
+#[Api(since: '1.0')]
+interface CloudEventCodec
+{
+    public function encode(MessageEnvelope $message): string;
+
+    /** @param EventData $data Public projected data; internal schema and metadata are omitted. */
+    public function encodeExternal(MessageEnvelope $message, array $data): string;
+
+    /** Decode the internal profile, including its required CoreX extensions. */
+    public function decode(string $json): MessageEnvelope;
+}
diff --git a/packages/core/src/Messaging/Contracts/MessagePublisher.php b/packages/core/src/Messaging/Contracts/MessagePublisher.php
index 0d2289a5..2dbe5225 100644
--- a/packages/core/src/Messaging/Contracts/MessagePublisher.php
+++ b/packages/core/src/Messaging/Contracts/MessagePublisher.php
@@ -15,11 +15,9 @@ use CoreX\Messaging\Data\MessageEnvelope;
  * переживающий offline consumer (D99). Реализация идемпотентна по messageId или
  * терпит повтор; окно дублей остаётся at-least-once.
  *
- * Wire-массив, который durable-реализации передают потребителю (P10, P14), содержит
- * ровно двенадцать ключей в объявленном порядке; `contract` — строка `name.vN`,
- * `occurredAt` — RFC 3339 с долями секунды. Перестановка ключей — ломающее изменение,
- * а не форматирование (как `ArrayDataScopeCodec`, P4.8). Канал публикации берётся из
- * объявления контракта в реестре, а не из полей конверта.
+ * Публикует байты CloudEventCodec::encode() — CloudEvents 1.0 structured JSON
+ * с media type application/cloudevents+json. Отображение — message-policy.md.
+ * Канал публикации берётся из объявления контракта в реестре.
  */
 #[Spi(kind: SpiKind::Implement, since: '1.0')]
 interface MessagePublisher
diff --git a/packages/core/src/Messaging/Data/MessageContract.php b/packages/core/src/Messaging/Data/MessageContract.php
index 8c0ee4e9..d72c114c 100644
--- a/packages/core/src/Messaging/Data/MessageContract.php
+++ b/packages/core/src/Messaging/Data/MessageContract.php
@@ -8,6 +8,7 @@ use CoreX\Attributes\Api;
 use CoreX\Messaging\Enums\MessageClass;
 use CoreX\Messaging\Enums\PiiClass;
 use CoreX\Messaging\Exceptions\InvalidMessage;
+use CoreX\Messaging\Internal\UriReference;
 
 /**
  * Объявление опубликованного контракта сообщения.
@@ -25,9 +26,14 @@ final readonly class MessageContract
         public string $owner,
         public PiiClass $maxPiiClass,
         public string $channel,
+        public ?string $dataSchema = null,
     ) {
         self::assertContractName($name);
 
+        if ($dataSchema !== null && ! UriReference::isAbsolute($dataSchema)) {
+            throw InvalidMessage::identity('contract data schema');
+        }
+
         if ($version < 1) {
             throw InvalidMessage::identity("contract version [{$version}]");
         }
diff --git a/packages/core/src/Messaging/Data/MessageEnvelope.php b/packages/core/src/Messaging/Data/MessageEnvelope.php
index 21813e53..43059823 100644
--- a/packages/core/src/Messaging/Data/MessageEnvelope.php
+++ b/packages/core/src/Messaging/Data/MessageEnvelope.php
@@ -8,6 +8,7 @@ use CoreX\Attributes\Api;
 use CoreX\Messaging\Contracts\DurablePayload;
 use CoreX\Messaging\Enums\PiiClass;
 use CoreX\Messaging\Exceptions\InvalidMessage;
+use CoreX\Messaging\Internal\UriReference;
 use DateTimeImmutable;
 
 /**
@@ -47,6 +48,10 @@ final readonly class MessageEnvelope implements DurablePayload
         self::assertOpaque(value: $messageId, name: 'message identity');
         self::assertOpaque(value: $source, name: 'message source');
 
+        if (! UriReference::isValid($source)) {
+            throw InvalidMessage::identity('message source');
+        }
+
         if ($contractVersion < 1) {
             throw InvalidMessage::identity("contract version [{$contractVersion}]");
         }
@@ -78,6 +83,18 @@ final readonly class MessageEnvelope implements DurablePayload
         return "{$this->name}.v{$this->contractVersion}";
     }
 
+    public function withPayload(MessagePayload $payload): self
+    {
+        return new self(
+            messageId: $this->messageId, source: $this->source, name: $this->name,
+            contractVersion: $this->contractVersion, occurredAt: $this->occurredAt,
+            scope: $this->scope, payload: $payload, piiClass: $this->piiClass,
+            subject: $this->subject, correlationId: $this->correlationId,
+            causationId: $this->causationId, metadata: $this->metadata,
+            envelopeVersion: $this->envelopeVersion,
+        );
+    }
+
     private static function assertOpaque(string $value, string $name): void
     {
         if ($value === '' || strlen($value) > 128 || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
diff --git a/packages/core/src/Messaging/Internal/JsonCloudEventCodec.php b/packages/core/src/Messaging/Internal/JsonCloudEventCodec.php
new file mode 100644
index 00000000..51a88763
--- /dev/null
+++ b/packages/core/src/Messaging/Internal/JsonCloudEventCodec.php
@@ -0,0 +1,263 @@
+<?php
+
+declare(strict_types=1);
+
+namespace CoreX\Messaging\Internal;
+
+use CoreX\Messaging\Contracts\CloudEventCodec;
+use CoreX\Messaging\Contracts\MessageContractRegistry;
+use CoreX\Messaging\Data\MessageEnvelope;
+use CoreX\Messaging\Data\MessagePayload;
+use CoreX\Messaging\Enums\PiiClass;
+use CoreX\Messaging\Exceptions\InvalidMessage;
+use DateTimeImmutable;
+use DateTimeZone;
+use Exception;
+use JsonException;
+use Override;
+use stdClass;
+
+/** @phpstan-import-type EventData from CloudEventCodec */
+final readonly class JsonCloudEventCodec implements CloudEventCodec
+{
+    private const int JsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
+
+    private const array Attributes = ['specversion', 'id', 'source', 'type', 'subject', 'time', 'datacontenttype', 'dataschema', 'corexscope', 'corexpii', 'corexenvelope', 'corexcorrelation', 'corexcausation', 'traceparent', 'tracestate', 'data'];
+
+    public function __construct(private MessageContractRegistry $contracts) {}
+
+    #[Override]
+    public function encode(MessageEnvelope $message): string
+    {
+        $schema = $this->contracts->get(name: $message->name, version: $message->contractVersion)->dataSchema;
+        self::assertMetadata($message->metadata);
+        $event = self::identity($message);
+
+        if ($message->subject !== null) {
+            $event['subject'] = $message->subject;
+        }
+
+        $event += self::content($message);
+
+        if ($schema !== null) {
+            $event['dataschema'] = $schema;
+        }
+
+        $event['corexscope'] = self::json((object) self::canonical($message->scope));
+        $event['corexpii'] = $message->piiClass->value;
+        $event['corexenvelope'] = $message->envelopeVersion;
+
+        foreach (['corexcorrelation' => $message->correlationId, 'corexcausation' => $message->causationId] as $key => $value) {
+            if ($value !== null) {
+                $event[$key] = $value;
+            }
+        }
+
+        foreach (['traceparent', 'tracestate'] as $key) {
+            if (isset($message->metadata[$key])) {
+                $event[$key] = $message->metadata[$key];
+            }
+        }
+
+        $event['data'] = (object) $message->payload->toArray();
+
+        return self::json($event);
+    }
+
+    /** @param EventData $data */
+    #[Override]
+    public function encodeExternal(MessageEnvelope $message, array $data): string
+    {
+        $this->contracts->get(name: $message->name, version: $message->contractVersion);
+        self::assertMetadata($message->metadata);
+
+        return self::json(self::identity($message) + self::content($message) + ['data' => self::objectData($data)]);
+    }
+
+    #[Override]
+    public function decode(string $json): MessageEnvelope
+    {
+        try {
+            $event = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
+        } catch (JsonException $e) {
+            throw InvalidMessage::payloadShape($e->getMessage());
+        }
+
+        if (! $event instanceof stdClass) {
+            throw InvalidMessage::payloadShape('CloudEvent must be an object');
+        }
+
+        $attributes = get_object_vars($event);
+
+        if (array_diff(array_keys($attributes), self::Attributes) !== []
+            || ($attributes['specversion'] ?? null) !== '1.0'
+            || ($attributes['datacontenttype'] ?? null) !== 'application/json'
+            || ($attributes['corexenvelope'] ?? null) !== MessageEnvelope::ENVELOPE_VERSION
+            || ! ($attributes['data'] ?? null) instanceof stdClass) {
+            throw InvalidMessage::identity('CloudEvent attributes');
+        }
+
+        foreach (['id', 'source', 'type', 'time', 'corexscope', 'corexpii'] as $key) {
+            if (! is_string($attributes[$key] ?? null) || $attributes[$key] === '') {
+                throw InvalidMessage::identity("CloudEvent {$key}");
+            }
+        }
+
+        foreach (['subject', 'dataschema', 'corexcorrelation', 'corexcausation', 'traceparent', 'tracestate'] as $key) {
+            if (array_key_exists($key, $attributes) && (! is_string($attributes[$key]) || $attributes[$key] === '')) {
+                throw InvalidMessage::identity("CloudEvent {$key}");
+            }
+        }
+
+        if (preg_match('/\A([a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*){1,3})\.v([1-9][0-9]*)\z/D', $attributes['type'], $type) !== 1
+            || strlen($type[2]) > strlen((string) PHP_INT_MAX)
+            || (strlen($type[2]) === strlen((string) PHP_INT_MAX) && strcmp($type[2], (string) PHP_INT_MAX) > 0)) {
+            throw InvalidMessage::identity('CloudEvent type');
+        }
+
+        $contract = $this->contracts->get(name: $type[1], version: (int) $type[2]);
+
+        $schema = self::optionalString($attributes, 'dataschema');
+
+        if ($schema !== null && (! UriReference::isAbsolute($schema) || $schema !== $contract->dataSchema)) {
+            throw InvalidMessage::identity('CloudEvent data schema');
+        }
+
+        $pii = PiiClass::tryFrom($attributes['corexpii']);
+
+        if ($pii === null) {
+            throw InvalidMessage::identity('CloudEvent PII class');
+        }
+
+        try {
+            $scopeObject = json_decode($attributes['corexscope'], false, 512, JSON_THROW_ON_ERROR);
+            $scope = json_decode($attributes['corexscope'], true, 512, JSON_THROW_ON_ERROR);
+        } catch (JsonException $e) {
+            throw InvalidMessage::payloadShape($e->getMessage());
+        }
+
+        if (! $scopeObject instanceof stdClass || ! is_array($scope)) {
+            throw InvalidMessage::identity('CloudEvent scope');
+        }
+
+        $metadata = array_intersect_key($attributes, array_flip(['traceparent', 'tracestate']));
+        self::assertMetadata($metadata);
+
+        /** @var array<string, scalar|null> $scope Object shape verified above. */
+        /** @var array<string, scalar|null> $metadata String attributes verified above. */
+        return new MessageEnvelope(
+            messageId: $attributes['id'], source: $attributes['source'], name: $type[1], contractVersion: (int) $type[2],
+            occurredAt: self::time($attributes['time']), scope: $scope,
+            payload: MessagePayload::fromJson(self::json($attributes['data'])), piiClass: $pii,
+            subject: self::optionalString($attributes, 'subject'), correlationId: self::optionalString($attributes, 'corexcorrelation'),
+            causationId: self::optionalString($attributes, 'corexcausation'), metadata: $metadata,
+        );
+    }
+
+    /** @return array<string, mixed> */
+    private static function identity(MessageEnvelope $message): array
+    {
+        return ['specversion' => '1.0', 'id' => $message->messageId, 'source' => $message->source, 'type' => $message->contract()];
+    }
+
+    /** @return array<string, mixed> */
+    private static function content(MessageEnvelope $message): array
+    {
+        return ['time' => $message->occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'), 'datacontenttype' => 'application/json'];
+    }
+
+    /** @param array<string, mixed> $metadata */
+    private static function assertMetadata(array $metadata): void
+    {
+        foreach ($metadata as $key => $value) {
+            if (! in_array($key, ['traceparent', 'tracestate'], true) || ! is_string($value) || $value === '') {
+                throw InvalidMessage::identity('CloudEvent trace metadata');
+            }
+        }
+
+        if (isset($metadata['tracestate']) && ! isset($metadata['traceparent'])) {
+            throw InvalidMessage::identity('CloudEvent traceparent');
+        }
+    }
+
+    /** @param array<array-key, mixed> $attributes */
+    private static function optionalString(array $attributes, string $key): ?string
+    {
+        if (! array_key_exists($key, $attributes)) {
+            return null;
+        }
+
+        $value = $attributes[$key];
+
+        if (! is_string($value) || $value === '') {
+            throw InvalidMessage::identity("CloudEvent {$key}");
+        }
+
+        return $value;
+    }
+
+    private static function time(string $value): DateTimeImmutable
+    {
+        if (preg_match('/\A\d{4}-\d{2}-\d{2}[Tt]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:[Zz]|[+-](?:[01]\d|2[0-3]):[0-5]\d)\z/D', $value) !== 1) {
+            throw InvalidMessage::identity('CloudEvent time');
+        }
+
+        try {
+            $time = new DateTimeImmutable($value);
+        } catch (Exception) {
+            throw InvalidMessage::identity('CloudEvent time');
+        }
+
+        if (DateTimeImmutable::getLastErrors() !== false) {
+            throw InvalidMessage::identity('CloudEvent time');
+        }
+
+        return $time;
+    }
+
+    /**
+     * @param  array<array-key, mixed>  $values
+     * @return array<array-key, mixed>
+     */
+    private static function canonical(array $values, int $depth = 0): array
+    {
+        if ($depth >= MessagePayload::MAX_DEPTH) {
+            throw InvalidMessage::payloadShape('nesting exceeds eight levels');
+        }
+
+        foreach ($values as $key => $value) {
+            if (is_array($value)) {
+                $values[$key] = self::canonical(values: $value, depth: $depth + 1);
+            } elseif ($value !== null && ! is_scalar($value)) {
+                throw InvalidMessage::payloadShape('contains an unsupported value');
+            }
+        }
+
+        if (! array_is_list($values)) {
+            ksort($values);
+        }
+
+        return $values;
+    }
+
+    /** @param array<array-key, mixed> $data */
+    private static function objectData(array $data): stdClass
+    {
+        foreach (array_keys($data) as $key) {
+            if (! is_string($key) || $key === '') {
+                throw InvalidMessage::payloadShape('external data must be an object');
+            }
+        }
+
+        return (object) self::canonical($data);
+    }
+
+    private static function json(mixed $value): string
+    {
+        try {
+            return json_encode($value, self::JsonFlags);
+        } catch (JsonException $e) {
+            throw InvalidMessage::payloadShape($e->getMessage());
+        }
+    }
+}
diff --git a/packages/core/src/Messaging/Internal/UriReference.php b/packages/core/src/Messaging/Internal/UriReference.php
new file mode 100644
index 00000000..55086e6d
--- /dev/null
+++ b/packages/core/src/Messaging/Internal/UriReference.php
@@ -0,0 +1,64 @@
+<?php
+
+declare(strict_types=1);
+
+namespace CoreX\Messaging\Internal;
+
+/** RFC 3986 generic syntax; not network reachability or scheme policy. */
+final class UriReference
+{
+    private const string Pattern = <<<'REGEX'
+~\A(?:(?&uri)|(?&relative))(?:\?(?&query))?(?:\#(?&query))?\z
+(?(DEFINE)
+    (?<unreserved> [A-Za-z0-9._\~-] )
+    (?<subdelims> [!$&'()*+,;=] )
+    (?<pct> %[0-9A-Fa-f]{2} )
+    (?<pchar> (?&unreserved)|(?&pct)|(?&subdelims)|[:@] )
+    (?<segment> (?&pchar)* )
+    (?<segmentnz> (?&pchar)+ )
+    (?<segmentnc> (?:(?&unreserved)|(?&pct)|(?&subdelims)|@)+ )
+    (?<abempty> (?:/(?&segment))* )
+    (?<absolute> /(?:(?&segmentnz)(?:/(?&segment))*)? )
+    (?<rootless> (?&segmentnz)(?:/(?&segment))* )
+    (?<noscheme> (?&segmentnc)(?:/(?&segment))* )
+    (?<scheme> [A-Za-z][A-Za-z0-9+.-]* )
+    (?<userinfo> (?:(?&unreserved)|(?&pct)|(?&subdelims)|:)* )
+    (?<regname> (?:(?&unreserved)|(?&pct)|(?&subdelims))* )
+    (?<octet> 25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9][0-9]|[0-9] )
+    (?<ipv4> (?&octet)\.(?&octet)\.(?&octet)\.(?&octet) )
+    (?<h16> [0-9A-Fa-f]{1,4} )
+    (?<ls32> (?&h16):(?&h16)|(?&ipv4) )
+    (?<ipv6>
+        (?:(?&h16):){6}(?&ls32)
+      | ::(?:(?&h16):){5}(?&ls32)
+      | (?&h16)?::(?:(?&h16):){4}(?&ls32)
+      | (?:(?:(?&h16):){0,1}(?&h16))?::(?:(?&h16):){3}(?&ls32)
+      | (?:(?:(?&h16):){0,2}(?&h16))?::(?:(?&h16):){2}(?&ls32)
+      | (?:(?:(?&h16):){0,3}(?&h16))?::(?&h16):(?&ls32)
+      | (?:(?:(?&h16):){0,4}(?&h16))?::(?&ls32)
+      | (?:(?:(?&h16):){0,5}(?&h16))?::(?&h16)
+      | (?:(?:(?&h16):){0,6}(?&h16))?::
+    )
+    (?<ipfuture> [vV][0-9A-Fa-f]+\.(?:(?&unreserved)|(?&subdelims)|:)+ )
+    (?<host> \[(?:(?&ipv6)|(?&ipfuture))\]|(?&regname) )
+    (?<authority> (?:(?&userinfo)@)?(?&host)(?::[0-9]*)? )
+    (?<hier> //(?&authority)(?&abempty)|(?&absolute)|(?&rootless)| )
+    (?<relative> //(?&authority)(?&abempty)|(?&absolute)|(?&noscheme)| )
+    (?<uri> (?&scheme):(?&hier) )
+    (?<query> (?:(?&pchar)|[/?])* )
+)
+~xD
+REGEX;
+
+    public static function isValid(string $value): bool
+    {
+        return preg_match(self::Pattern, $value) === 1;
+    }
+
+    public static function isAbsolute(string $value): bool
+    {
+        return self::isValid($value)
+            && preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:/D', $value) === 1
+            && ! str_contains($value, '#');
+    }
+}
diff --git a/packages/core/src/Messaging/Laravel/MessagingRegistrar.php b/packages/core/src/Messaging/Laravel/MessagingRegistrar.php
index f7847001..9b4926cf 100644
--- a/packages/core/src/Messaging/Laravel/MessagingRegistrar.php
+++ b/packages/core/src/Messaging/Laravel/MessagingRegistrar.php
@@ -8,6 +8,7 @@ use CoreX\Attributes\Spi;
 use CoreX\Attributes\SpiKind;
 use CoreX\Config\Data\CoreOptions;
 use CoreX\Config\Internal\ApplicationLocator;
+use CoreX\Messaging\Contracts\CloudEventCodec;
 use CoreX\Messaging\Contracts\Inbox;
 use CoreX\Messaging\Contracts\MessageContractRegistry;
 use CoreX\Messaging\Contracts\Outbox;
@@ -19,6 +20,7 @@ use CoreX\Messaging\Enums\PubSubDriver;
 use CoreX\Messaging\Internal\ArrayMessageContractRegistry;
 use CoreX\Messaging\Internal\DatabasePollPubSub;
 use CoreX\Messaging\Internal\DefaultWorkerRuntime;
+use CoreX\Messaging\Internal\JsonCloudEventCodec;
 use CoreX\Messaging\Internal\NullPubSub;
 use CoreX\Messaging\Internal\NullTraceContextBridge;
 use CoreX\Messaging\Internal\RedisPubSub;
@@ -44,6 +46,9 @@ final class MessagingRegistrar implements PackageRegistrar
             MessageContractRegistry::class,
             static fn (): ArrayMessageContractRegistry => new ArrayMessageContractRegistry(contracts: []),
         );
+        $app->scoped(CloudEventCodec::class, static fn (Application $app): JsonCloudEventCodec => new JsonCloudEventCodec(
+            contracts: $app->make(MessageContractRegistry::class),
+        ));
         $app->instance(TraceContextBridge::class, new NullTraceContextBridge);
         $app->scoped(
             Outbox::class,
diff --git a/packages/core/tests/Unit/CloudEventCodecTest.php b/packages/core/tests/Unit/CloudEventCodecTest.php
new file mode 100644
index 00000000..0afd7c44
--- /dev/null
+++ b/packages/core/tests/Unit/CloudEventCodecTest.php
@@ -0,0 +1,104 @@
+<?php
+
+declare(strict_types=1);
+
+use CoreX\Messaging\Data\MessageContract;
+use CoreX\Messaging\Data\MessageEnvelope;
+use CoreX\Messaging\Data\MessagePayload;
+use CoreX\Messaging\Enums\MessageClass;
+use CoreX\Messaging\Enums\PiiClass;
+use CoreX\Messaging\Exceptions\InvalidMessage;
+use CoreX\Messaging\Exceptions\UnknownMessageContract;
+use CoreX\Messaging\Internal\ArrayMessageContractRegistry;
+use CoreX\Messaging\Internal\JsonCloudEventCodec;
+
+function cloudEventCodec(?string $schema = 'https://schemas.example.test/customfields.attribute.changed/v1.json'): JsonCloudEventCodec
+{
+    return new JsonCloudEventCodec(new ArrayMessageContractRegistry([
+        new MessageContract('customfields.attribute.changed', 1, MessageClass::IntegrationEvent, 'axiomasoft/corex-custom-fields', PiiClass::Personal, 'events', $schema),
+    ]));
+}
+
+function cloudEventMessage(bool $optional = true): MessageEnvelope
+{
+    return new MessageEnvelope(
+        messageId: '01J9Z6Q4A7', source: 'axiomasoft/corex-custom-fields', name: 'customfields.attribute.changed', contractVersion: 1,
+        occurredAt: new DateTimeImmutable('2026-10-03T08:00:00.123456Z'), scope: ['workspace' => 'w-7', 'account' => 'a-1'],
+        payload: new MessagePayload(['attribute' => 'color']), piiClass: PiiClass::None, subject: $optional ? 'attr-42' : null,
+        correlationId: $optional ? 'c-1' : null,
+        metadata: $optional ? ['traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'] : [],
+    );
+}
+
+it('encodes the byte exact CloudEvents example and round trips', function (): void {
+    $codec = cloudEventCodec();
+    $expected = '{"specversion":"1.0","id":"01J9Z6Q4A7","source":"axiomasoft/corex-custom-fields","type":"customfields.attribute.changed.v1","subject":"attr-42","time":"2026-10-03T08:00:00.123456Z","datacontenttype":"application/json","dataschema":"https://schemas.example.test/customfields.attribute.changed/v1.json","corexscope":"{\\"account\\":\\"a-1\\",\\"workspace\\":\\"w-7\\"}","corexpii":"none","corexenvelope":1,"corexcorrelation":"c-1","traceparent":"00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01","data":{"attribute":"color"}}';
+    expect($codec->encode(cloudEventMessage()))->toBe($expected)
+        ->and($codec->decode($expected))->toEqual(cloudEventMessage());
+    $plain = cloudEventMessage(false);
+    $plainCodec = cloudEventCodec(null);
+    expect($plainCodec->decode($plainCodec->encode($plain)))->toEqual($plain);
+    $empty = $plain->withPayload(MessagePayload::empty());
+    expect(json_decode($plainCodec->encode($empty))->data)->toBeInstanceOf(stdClass::class)
+        ->and($plainCodec->decode($plainCodec->encode($empty)))->toEqual($empty);
+});
+
+it('external CloudEvents contains only public attributes and projected data', function (): void {
+    $message = cloudEventMessage()->withPayload(new MessagePayload(['id' => 'attr-42', 'secret' => 'hidden']));
+    $wire = cloudEventCodec()->encodeExternal($message, ['id' => 'attr-42']);
+    expect($wire)->toBe('{"specversion":"1.0","id":"01J9Z6Q4A7","source":"axiomasoft/corex-custom-fields","type":"customfields.attribute.changed.v1","time":"2026-10-03T08:00:00.123456Z","datacontenttype":"application/json","data":{"id":"attr-42"}}')
+        ->and($wire)->not->toContain('hidden', 'dataschema', 'corexscope', 'traceparent', 'subject');
+    expect(json_decode(cloudEventCodec()->encode($message), true)['dataschema'])->toBe('https://schemas.example.test/customfields.attribute.changed/v1.json');
+});
+
+it('rejects malformed CloudEvents', function (array $change): void {
+    $codec = cloudEventCodec();
+    $event = json_decode($codec->encode(cloudEventMessage()), true, flags: JSON_THROW_ON_ERROR);
+    $codec->decode(json_encode(array_replace($event, $change), JSON_THROW_ON_ERROR));
+})->with([
+    'old spec' => [['specversion' => '0.3']], 'unknown' => [['foo' => 'x']], 'base64' => [['data_base64' => 'eA==']],
+    'unversioned' => [['type' => 'customfields.attribute.changed']], 'zoneless' => [['time' => '2026-10-03T08:00:00']],
+    'scope list' => [['corexscope' => '[]']], 'bad date' => [['time' => '2026-02-30T08:00:00Z']],
+    'invalid pii' => [['corexpii' => 'secret']], 'string envelope' => [['corexenvelope' => '1']],
+    'data list' => [['data' => [1, 2]]], 'null optional' => [['subject' => null]], 'invalid trace' => [['traceparent' => []]],
+])->throws(InvalidMessage::class);
+
+it('rejects broken JSON and non object events', function (string $wire): void {
+    cloudEventCodec()->decode($wire);
+})->with(['{', '[]', 'null', '{"specversion":"1.0"}'])->throws(InvalidMessage::class);
+
+it('rejects unknown metadata and tracestate without traceparent', function (array $metadata): void {
+    $m = cloudEventMessage();
+    $bad = new MessageEnvelope($m->messageId, $m->source, $m->name, 1, $m->occurredAt, $m->scope, $m->payload, $m->piiClass, metadata: $metadata);
+    cloudEventCodec()->encode($bad);
+})->with([[['x-tenant' => 'a']], [['tracestate' => 'vendor=value']], [['traceparent' => null]]])->throws(InvalidMessage::class);
+
+it('rejects undeclared contracts', function (): void {
+    (new JsonCloudEventCodec(new ArrayMessageContractRegistry([])))->encode(cloudEventMessage());
+})->throws(UnknownMessageContract::class);
+
+it('accepts URI references in source', function (string $source): void {
+    $m = cloudEventMessage();
+    expect(new MessageEnvelope('id', $source, $m->name, 1, $m->occurredAt, $m->scope, $m->payload, $m->piiClass))->source->toBe($source);
+})->with(['corex/relay', 'axiomasoft/corex-custom-fields', 'fixture-address/1', './1:foo', '/a/b', '../a', '?q=a%20b', '#fragment', 'urn:corex:source', 'https://example.test/a%20b?q=x#f', 'https://[::1]/', 'https://[2001:db8::1]:443/a', 'https://[::ffff:192.0.2.1]/', 'https://[v1.a:b]/']);
+
+it('rejects malformed source URI references', function (string $source): void {
+    $m = cloudEventMessage();
+    new MessageEnvelope('id', $source, $m->name, 1, $m->occurredAt, $m->scope, $m->payload, $m->piiClass);
+})->with(['', str_repeat('x', 129), 'has space', "a\n", 'источник', '%', '%zz', 'a%2', '1:foo', 'http://[not-ip]/', 'https://[::1', 'https://[1::2::3]/', 'https://host:port/', 'https://host/a[b]', 'https://host/#a#b'])->throws(InvalidMessage::class);
+
+it('accepts absolute schema URIs', function (string $schema): void {
+    expect(cloudEventCodec($schema)->encode(cloudEventMessage()))->toContain('"dataschema":"'.$schema.'"');
+})->with(['urn:corex:schema:v1', 'https://schemas.example.test/v1.json']);
+
+it('rejects invalid schema URIs', function (string $schema): void {
+    cloudEventCodec($schema);
+})->with(['relative/path', 'https://host/%zz', 'https://[bad]/', 'https://host/schema#fragment'])->throws(InvalidMessage::class);
+
+it('bounds external JSON trees and rejects non JSON values', function (): void {
+    $data = ['value' => 'leaf'];
+    for ($depth = 0; $depth < 8; $depth++) {
+        $data = ['nested' => $data];
+    }
+    cloudEventCodec()->encodeExternal(cloudEventMessage(), $data);
+})->throws(InvalidMessage::class);
diff --git a/packages/webhooks/src/Contracts/WebhookPayloadProjector.php b/packages/webhooks/src/Contracts/WebhookPayloadProjector.php
index 12ace4ff..88c0ebf5 100644
--- a/packages/webhooks/src/Contracts/WebhookPayloadProjector.php
+++ b/packages/webhooks/src/Contracts/WebhookPayloadProjector.php
@@ -6,20 +6,18 @@ namespace CoreX\Webhooks\Contracts;
 
 use CoreX\Attributes\Spi;
 use CoreX\Attributes\SpiKind;
+use CoreX\Messaging\Contracts\CloudEventCodec;
 use CoreX\Messaging\Data\MessageEnvelope;
 use CoreX\Webhooks\Data\WebhookTopic;
 
 /**
- * Explicit public export of one declared topic.
+ * Installed field allow-list projector: returns only public data for the external CloudEvent.
  *
- * The service id is resolved from the installed code map. A class or event name
- * from JSON is not a projector.
+ * @phpstan-import-type EventData from CloudEventCodec
  */
 #[Spi(kind: SpiKind::Implement, since: '1.0')]
 interface WebhookPayloadProjector
 {
-    /**
-     * @return array{event_id: string, type: string, version: int, occurred_at: string, data: array<string, string>, correlation_id: string|null}
-     */
+    /** @return EventData Projected JSON object; never an envelope or internal metadata. */
     public function project(MessageEnvelope $message, WebhookTopic $topic): array;
 }
diff --git a/packages/webhooks/src/Internal/CurlWebhookTransport.php b/packages/webhooks/src/Internal/CurlWebhookTransport.php
index 8b1fb401..e254d0a5 100644
--- a/packages/webhooks/src/Internal/CurlWebhookTransport.php
+++ b/packages/webhooks/src/Internal/CurlWebhookTransport.php
@@ -140,7 +140,7 @@ final class CurlWebhookTransport implements WebhookHttpTransport
     private function requestHeaders(ValidatedWebhookRequest $request): array
     {
         return [
-            'Content-Type: application/json',
+            'Content-Type: application/cloudevents+json',
             'Webhook-Id: '.$request->signatureHeaders['webhook-id'],
             'Webhook-Timestamp: '.$request->signatureHeaders['webhook-timestamp'],
             'Webhook-Signature: '.$request->signatureHeaders['webhook-signature'],
diff --git a/packages/webhooks/src/Internal/WebhookFanout.php b/packages/webhooks/src/Internal/WebhookFanout.php
index 40e58785..e1b0267c 100644
--- a/packages/webhooks/src/Internal/WebhookFanout.php
+++ b/packages/webhooks/src/Internal/WebhookFanout.php
@@ -4,6 +4,7 @@ declare(strict_types=1);
 
 namespace CoreX\Webhooks\Internal;
 
+use CoreX\Messaging\Contracts\CloudEventCodec;
 use CoreX\Messaging\Contracts\Inbox;
 use CoreX\Messaging\Data\MessageEnvelope;
 use CoreX\Operations\Validation\Laravel\JsonCanonical;
@@ -29,6 +30,7 @@ final readonly class WebhookFanout
         private WebhookTopicCatalogue $catalogue,
         private array $projectors,
         private ClockInterface $clock,
+        private CloudEventCodec $codec,
     ) {}
 
     public function accept(MessageEnvelope $message): void
@@ -57,9 +59,9 @@ final readonly class WebhookFanout
             }
 
             $projected = $projector->project($message, $topic);
-            $encoded = JsonCanonical::encode($projected);
+            $encoded = $this->codec->encodeExternal($message, $projected);
             $oversize = strlen($encoded) > self::BodyLimit;
-            $body = $oversize ? JsonCanonical::encode(['error' => 'payload_too_large']) : $encoded;
+            $body = $oversize ? $this->codec->encodeExternal($message, ['error' => 'payload_too_large']) : $encoded;
             $now = $this->clock->now();
 
             foreach ($rows as $row) {
diff --git a/packages/webhooks/src/Laravel/WebhookBindingsRegistrar.php b/packages/webhooks/src/Laravel/WebhookBindingsRegistrar.php
index 0ba7acc4..a67d6b12 100644
--- a/packages/webhooks/src/Laravel/WebhookBindingsRegistrar.php
+++ b/packages/webhooks/src/Laravel/WebhookBindingsRegistrar.php
@@ -8,6 +8,7 @@ use CoreX\Attributes\Spi;
 use CoreX\Attributes\SpiKind;
 use CoreX\Audit\Contracts\AuditLogger;
 use CoreX\Context\Contracts\TenantContextRestorer;
+use CoreX\Messaging\Contracts\CloudEventCodec;
 use CoreX\Messaging\Contracts\Inbox;
 use CoreX\Messaging\Contracts\MessageContractRegistry;
 use CoreX\Modules\Contracts\PackageRegistrar;
@@ -85,6 +86,7 @@ final class WebhookBindingsRegistrar implements PackageRegistrar
             catalogue: $app->make(WebhookTopicCatalogue::class),
             projectors: self::projectors($app, $app->make(WebhookOptions::class)),
             clock: $app->make(ClockInterface::class),
+            codec: $app->make(CloudEventCodec::class),
         ));
         $app->scoped(WebhookRetention::class, static fn (Application $app): WebhookRetention => new WebhookRetention(
             connection: $app->make(ApiSchemaRegistrar::class)->tenant(),
diff --git a/packages/webhooks/tests/Feature/WebhookDeliveryTest.php b/packages/webhooks/tests/Feature/WebhookDeliveryTest.php
index e7917f2a..4febc1da 100644
--- a/packages/webhooks/tests/Feature/WebhookDeliveryTest.php
+++ b/packages/webhooks/tests/Feature/WebhookDeliveryTest.php
@@ -370,13 +370,6 @@ final class DeliveryProjector implements WebhookPayloadProjector
 {
     public function project(MessageEnvelope $message, WebhookTopic $topic): array
     {
-        return [
-            'event_id' => $message->messageId,
-            'type' => $message->name,
-            'version' => $message->contractVersion,
-            'occurred_at' => $message->occurredAt->format(DateTimeImmutable::ATOM),
-            'data' => [],
-            'correlation_id' => $message->correlationId,
-        ];
+        return [];
     }
 }
diff --git a/packages/webhooks/tests/Feature/WebhookFanoutTest.php b/packages/webhooks/tests/Feature/WebhookFanoutTest.php
index 103847e1..362516ee 100644
--- a/packages/webhooks/tests/Feature/WebhookFanoutTest.php
+++ b/packages/webhooks/tests/Feature/WebhookFanoutTest.php
@@ -5,6 +5,7 @@ declare(strict_types=1);
 namespace CoreX\Webhooks\Tests\Feature;
 
 use CoreX\Config\Data\CoreOptions;
+use CoreX\Config\Data\SecretRef;
 use CoreX\Context\Data\AccountRef;
 use CoreX\CoreServiceProvider;
 use CoreX\Messaging\Contracts\DurableMessagePublisher;
@@ -14,6 +15,7 @@ use CoreX\Messaging\Data\MessageEnvelope;
 use CoreX\Messaging\Data\MessagePayload;
 use CoreX\Messaging\Enums\MessageClass;
 use CoreX\Messaging\Enums\PiiClass;
+use CoreX\Messaging\Internal\JsonCloudEventCodec;
 use CoreX\Messaging\Testing\MessagingFixtures;
 use CoreX\Operations\Laravel\ApiSchemaRegistrar;
 use CoreX\Operations\Testing\ContextFixture;
@@ -22,7 +24,9 @@ use CoreX\Time\Testing\FrozenClock;
 use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
 use CoreX\Webhooks\Data\WebhookSubscription;
 use CoreX\Webhooks\Data\WebhookTopic;
+use CoreX\Webhooks\Internal\HmacWebhookSigner;
 use CoreX\Webhooks\Internal\WebhookFanout;
+use CoreX\Webhooks\Internal\WebhookKeyRing;
 use CoreX\Webhooks\Internal\WebhookMessagePublisher;
 use CoreX\Webhooks\Internal\WebhookTopicCatalogue;
 use CoreX\Webhooks\Tests\TestCase;
@@ -99,6 +103,17 @@ final class WebhookFanoutTest extends TestCase
         self::assertSame('https://hooks.example/receiver', $rows[0]->endpoint_snapshot);
         self::assertSame(3, $forwarded);
         self::assertStringNotContainsString('hidden', (string) $rows[0]->body);
+        $event = json_decode((string) $rows[0]->body, true, flags: JSON_THROW_ON_ERROR);
+        self::assertSame(['specversion', 'id', 'source', 'type', 'time', 'datacontenttype', 'data'], array_keys($event));
+        self::assertSame('1.0', $event['specversion']);
+        self::assertSame('orders.placed.v1', $event['type']);
+        self::assertSame(['id' => 'order-1'], $event['data']);
+        self::assertArrayNotHasKey('dataschema', $event);
+        $keys = new WebhookKeyRing;
+        $keys->provision('current', new SecretRef('whsec_'.base64_encode(str_repeat('s', 32))), 0);
+        $signer = new HmacWebhookSigner($keys);
+        $signed = $signer->sign((string) $rows[0]->id, 1790337600, (string) $rows[0]->body, ['current']);
+        self::assertSame(['v1,'.base64_encode(hash_hmac('sha256', $rows[0]->id.'.1790337600.'.$rows[0]->body, str_repeat('s', 32), true))], $signed->signatures);
     }
 
     public function test_oversize_payload_is_dead_and_a_lossy_publisher_is_rejected(): void
@@ -185,11 +200,12 @@ final class WebhookFanoutTest extends TestCase
         $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
         $schema = ['id' => 'string'];
         $topic = new WebhookTopic('orders.placed', 1, 'v1', $schema, hash('sha256', JsonCanonical::encode($schema)), 'personal', 'orders.placed');
-        $catalogue = new WebhookTopicCatalogue(MessagingFixtures::contracts([
-            new MessageContract('orders.placed', 1, MessageClass::IntegrationEvent, 'orders/api', PiiClass::Personal, 'webhooks'),
-        ]), [$topic], ['orders.placed' => $projector]);
+        $contracts = MessagingFixtures::contracts([
+            new MessageContract('orders.placed', 1, MessageClass::IntegrationEvent, 'orders/api', PiiClass::Personal, 'webhooks', 'https://schemas.example.test/orders/v1.json'),
+        ]);
+        $catalogue = new WebhookTopicCatalogue($contracts, [$topic], ['orders.placed' => $projector]);
 
-        return new WebhookFanout($db, MessagingFixtures::inbox($db, $this->clock), $catalogue, ['orders.placed' => $projector], $this->clock);
+        return new WebhookFanout($db, MessagingFixtures::inbox($db, $this->clock), $catalogue, ['orders.placed' => $projector], $this->clock, new JsonCloudEventCodec($contracts));
     }
 
     private function subscribe(string $id, string $connection = 'api_tenant'): void
@@ -264,14 +280,7 @@ final class ExportProjector implements WebhookPayloadProjector
 {
     public function project(MessageEnvelope $message, WebhookTopic $topic): array
     {
-        return [
-            'event_id' => $message->messageId,
-            'type' => $message->name,
-            'version' => $message->contractVersion,
-            'occurred_at' => '2026-09-25T12:00:00+00:00',
-            'data' => ['id' => 'order-1'],
-            'correlation_id' => $message->correlationId,
-        ];
+        return ['id' => 'order-1'];
     }
 }
 
@@ -287,13 +296,6 @@ final class HugeProjector implements WebhookPayloadProjector
 {
     public function project(MessageEnvelope $message, WebhookTopic $topic): array
     {
-        return [
-            'event_id' => $message->messageId,
-            'type' => $message->name,
-            'version' => $message->contractVersion,
-            'occurred_at' => '2026-09-25T12:00:00+00:00',
-            'data' => ['id' => str_repeat('x', WebhookFanout::BodyLimit)],
-            'correlation_id' => null,
-        ];
+        return ['id' => str_repeat('x', WebhookFanout::BodyLimit)];
     }
 }
diff --git a/packages/webhooks/tests/Feature/WebhookLoadContourIntegrationTest.php b/packages/webhooks/tests/Feature/WebhookLoadContourIntegrationTest.php
index e9349bc4..498b65c0 100644
--- a/packages/webhooks/tests/Feature/WebhookLoadContourIntegrationTest.php
+++ b/packages/webhooks/tests/Feature/WebhookLoadContourIntegrationTest.php
@@ -19,6 +19,7 @@ use CoreX\Messaging\Data\MessageEnvelope;
 use CoreX\Messaging\Data\MessagePayload;
 use CoreX\Messaging\Enums\MessageClass;
 use CoreX\Messaging\Enums\PiiClass;
+use CoreX\Messaging\Internal\JsonCloudEventCodec;
 use CoreX\Messaging\Testing\MessagingFixtures;
 use CoreX\Modules\Contracts\ModuleRegistry;
 use CoreX\Modules\ModulesServiceProvider;
@@ -131,7 +132,7 @@ final class WebhookLoadContourIntegrationTest extends TestCase
             });
             $clock = new FrozenClock(new DateTimeImmutable('2026-09-26T12:00:00.000000+00:00'));
             $catalogue = $this->catalogue();
-            $fanout = new WebhookFanout($connection, MessagingFixtures::inbox($connection, $clock), $catalogue, ['orders.placed' => new WebhookLoadContourIntegrationLoadContourProjector], $clock);
+            $fanout = new WebhookFanout($connection, MessagingFixtures::inbox($connection, $clock), $catalogue, ['orders.placed' => new WebhookLoadContourIntegrationLoadContourProjector], $clock, new JsonCloudEventCodec(MessagingFixtures::contracts([new MessageContract('orders.placed', 1, MessageClass::IntegrationEvent, 'orders/api', PiiClass::Personal, 'webhooks')])));
             $now = $clock->now();
             $connection->table('sys_api_webhook_subscriptions')->insert([
                 'id' => 'sub-local',
@@ -314,13 +315,6 @@ final class WebhookLoadContourIntegrationLoadContourProjector implements Webhook
 {
     public function project(MessageEnvelope $message, WebhookTopic $topic): array
     {
-        return [
-            'event_id' => $message->messageId,
-            'type' => $message->name,
-            'version' => $message->contractVersion,
-            'occurred_at' => '2026-09-26T12:00:00+00:00',
-            'data' => ['id' => 'order-1'],
-            'correlation_id' => $message->correlationId,
-        ];
+        return ['id' => 'order-1'];
     }
 }
diff --git a/packages/webhooks/tests/Feature/WebhookManagementTest.php b/packages/webhooks/tests/Feature/WebhookManagementTest.php
index 98934537..6f591533 100644
--- a/packages/webhooks/tests/Feature/WebhookManagementTest.php
+++ b/packages/webhooks/tests/Feature/WebhookManagementTest.php
@@ -315,14 +315,7 @@ final class ManagementProjector implements WebhookPayloadProjector
 {
     public function project(MessageEnvelope $message, WebhookTopic $topic): array
     {
-        return [
-            'event_id' => $message->messageId,
-            'type' => $topic->name,
-            'version' => $topic->version,
-            'occurred_at' => $message->occurredAt->format(DATE_ATOM),
-            'data' => [],
-            'correlation_id' => $message->correlationId,
-        ];
+        return [];
     }
 }
 
diff --git a/packages/webhooks/tests/Feature/WebhookReplayTest.php b/packages/webhooks/tests/Feature/WebhookReplayTest.php
index 1c27e04e..a1a32f0e 100644
--- a/packages/webhooks/tests/Feature/WebhookReplayTest.php
+++ b/packages/webhooks/tests/Feature/WebhookReplayTest.php
@@ -404,13 +404,6 @@ final class ReplayProjector implements WebhookPayloadProjector
 {
     public function project(MessageEnvelope $message, WebhookTopic $topic): array
     {
-        return [
-            'event_id' => $message->messageId,
-            'type' => $topic->name,
-            'version' => $topic->version,
-            'occurred_at' => $message->occurredAt->format(DATE_ATOM),
-            'data' => [],
-            'correlation_id' => $message->correlationId,
-        ];
+        return [];
     }
 }
diff --git a/packages/webhooks/tests/Feature/WebhookSubscriptionTest.php b/packages/webhooks/tests/Feature/WebhookSubscriptionTest.php
index 262a8aa6..9f91c52b 100644
--- a/packages/webhooks/tests/Feature/WebhookSubscriptionTest.php
+++ b/packages/webhooks/tests/Feature/WebhookSubscriptionTest.php
@@ -213,15 +213,8 @@ final class WebhookSubscriptionTest extends TestCase
         );
         $projected = (new AllowListProjector)->project($envelope, $topic);
 
-        self::assertSame([
-            'event_id' => 'message-1',
-            'type' => 'orders.placed',
-            'version' => 1,
-            'occurred_at' => '2026-09-25T12:00:00+00:00',
-            'data' => ['id' => 'order-1'],
-            'correlation_id' => 'correlation-1',
-        ], $projected);
-        self::assertArrayNotHasKey('secret', $projected['data']);
+        self::assertSame(['id' => 'order-1'], $projected);
+        self::assertArrayNotHasKey('secret', $projected);
     }
 
     private function catalogue(bool $secret = false): WebhookTopicCatalogue
@@ -302,13 +295,6 @@ final class AllowListProjector implements WebhookPayloadProjector
             $data[$field] = $value;
         }
 
-        return [
-            'event_id' => $message->messageId,
-            'type' => $message->name,
-            'version' => $message->contractVersion,
-            'occurred_at' => $message->occurredAt->format(DateTimeImmutable::ATOM),
-            'data' => $data,
-            'correlation_id' => $message->correlationId,
-        ];
+        return $data;
     }
 }
diff --git a/packages/webhooks/tests/Feature/WebhookTransportTest.php b/packages/webhooks/tests/Feature/WebhookTransportTest.php
index f11901d7..ac5c9deb 100644
--- a/packages/webhooks/tests/Feature/WebhookTransportTest.php
+++ b/packages/webhooks/tests/Feature/WebhookTransportTest.php
@@ -164,7 +164,7 @@ final class WebhookTransportTest extends TestCase
         $this->assertSame('https://hooks.example.com/deliver', $options[CURLOPT_URL]);
         $this->assertSame('{"event":"created"}', $options[CURLOPT_POSTFIELDS]);
         $this->assertSame([
-            'Content-Type: application/json',
+            'Content-Type: application/cloudevents+json',
             'Webhook-Id: delivery_123',
             'Webhook-Timestamp: 1700000000',
             'Webhook-Signature: v1,YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=',
@@ -294,7 +294,7 @@ PHP;
                 CURLOPT_POST => true,
                 CURLOPT_POSTFIELDS => '{"event":"created"}',
                 CURLOPT_HTTPHEADER => [
-                    'Content-Type: application/json',
+                    'Content-Type: application/cloudevents+json',
                     'Webhook-Id: delivery_123',
                 ],
                 CURLOPT_CONNECT_TO => ["hooks.example.com:443:127.0.0.1:{$port}"],

FULL SOURCE packages/core/src/Messaging/Contracts/CloudEventCodec.php SHA256 b64113ca6eec16fc48ec1c305d07be88cd68d7b18eaeadb0c4fb8fa28254e8c5
1: <?php
2: 
3: declare(strict_types=1);
4: 
5: namespace CoreX\Messaging\Contracts;
6: 
7: use CoreX\Attributes\Api;
8: use CoreX\Messaging\Data\MessageEnvelope;
9: 
10: /**
11:  * CloudEvents 1.0 structured JSON with internal and external profiles.
12:  * Projected data uses the same eight-level JSON tree boundary as message payloads, including JSON numbers.
13:  *
14:  * @phpstan-type EventLevel0 null|bool|int|float|string
15:  * @phpstan-type EventLevel1 null|bool|int|float|string|array<array-key, EventLevel0>
16:  * @phpstan-type EventLevel2 null|bool|int|float|string|array<array-key, EventLevel1>
17:  * @phpstan-type EventLevel3 null|bool|int|float|string|array<array-key, EventLevel2>
18:  * @phpstan-type EventLevel4 null|bool|int|float|string|array<array-key, EventLevel3>
19:  * @phpstan-type EventLevel5 null|bool|int|float|string|array<array-key, EventLevel4>
20:  * @phpstan-type EventLevel6 null|bool|int|float|string|array<array-key, EventLevel5>
21:  * @phpstan-type EventLevel7 null|bool|int|float|string|array<array-key, EventLevel6>
22:  * @phpstan-type EventData array<string, EventLevel7>
23:  */
24: #[Api(since: '1.0')]
25: interface CloudEventCodec
26: {
27:     public function encode(MessageEnvelope $message): string;
28: 
29:     /** @param EventData $data Public projected data; internal schema and metadata are omitted. */
30:     public function encodeExternal(MessageEnvelope $message, array $data): string;
31: 
32:     /** Decode the internal profile, including its required CoreX extensions. */
33:     public function decode(string $json): MessageEnvelope;
34: }

FULL SOURCE packages/core/src/Messaging/Internal/JsonCloudEventCodec.php SHA256 0d5f3201a4d72b271bf15a8c948922bfa068bdb1c1cbdaa1b0551f2c87467a4b
1: <?php
2: 
3: declare(strict_types=1);
4: 
5: namespace CoreX\Messaging\Internal;
6: 
7: use CoreX\Messaging\Contracts\CloudEventCodec;
8: use CoreX\Messaging\Contracts\MessageContractRegistry;
9: use CoreX\Messaging\Data\MessageEnvelope;
10: use CoreX\Messaging\Data\MessagePayload;
11: use CoreX\Messaging\Enums\PiiClass;
12: use CoreX\Messaging\Exceptions\InvalidMessage;
13: use DateTimeImmutable;
14: use DateTimeZone;
15: use Exception;
16: use JsonException;
17: use Override;
18: use stdClass;
19: 
20: /** @phpstan-import-type EventData from CloudEventCodec */
21: final readonly class JsonCloudEventCodec implements CloudEventCodec
22: {
23:     private const int JsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
24: 
25:     private const array Attributes = ['specversion', 'id', 'source', 'type', 'subject', 'time', 'datacontenttype', 'dataschema', 'corexscope', 'corexpii', 'corexenvelope', 'corexcorrelation', 'corexcausation', 'traceparent', 'tracestate', 'data'];
26: 
27:     public function __construct(private MessageContractRegistry $contracts) {}
28: 
29:     #[Override]
30:     public function encode(MessageEnvelope $message): string
31:     {
32:         $schema = $this->contracts->get(name: $message->name, version: $message->contractVersion)->dataSchema;
33:         self::assertMetadata($message->metadata);
34:         $event = self::identity($message);
35: 
36:         if ($message->subject !== null) {
37:             $event['subject'] = $message->subject;
38:         }
39: 
40:         $event += self::content($message);
41: 
42:         if ($schema !== null) {
43:             $event['dataschema'] = $schema;
44:         }
45: 
46:         $event['corexscope'] = self::json((object) self::canonical($message->scope));
47:         $event['corexpii'] = $message->piiClass->value;
48:         $event['corexenvelope'] = $message->envelopeVersion;
49: 
50:         foreach (['corexcorrelation' => $message->correlationId, 'corexcausation' => $message->causationId] as $key => $value) {
51:             if ($value !== null) {
52:                 $event[$key] = $value;
53:             }
54:         }
55: 
56:         foreach (['traceparent', 'tracestate'] as $key) {
57:             if (isset($message->metadata[$key])) {
58:                 $event[$key] = $message->metadata[$key];
59:             }
60:         }
61: 
62:         $event['data'] = (object) $message->payload->toArray();
63: 
64:         return self::json($event);
65:     }
66: 
67:     /** @param EventData $data */
68:     #[Override]
69:     public function encodeExternal(MessageEnvelope $message, array $data): string
70:     {
71:         $this->contracts->get(name: $message->name, version: $message->contractVersion);
72:         self::assertMetadata($message->metadata);
73: 
74:         return self::json(self::identity($message) + self::content($message) + ['data' => self::objectData($data)]);
75:     }
76: 
77:     #[Override]
78:     public function decode(string $json): MessageEnvelope
79:     {
80:         try {
81:             $event = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
82:         } catch (JsonException $e) {
83:             throw InvalidMessage::payloadShape($e->getMessage());
84:         }
85: 
86:         if (! $event instanceof stdClass) {
87:             throw InvalidMessage::payloadShape('CloudEvent must be an object');
88:         }
89: 
90:         $attributes = get_object_vars($event);
91: 
92:         if (array_diff(array_keys($attributes), self::Attributes) !== []
93:             || ($attributes['specversion'] ?? null) !== '1.0'
94:             || ($attributes['datacontenttype'] ?? null) !== 'application/json'
95:             || ($attributes['corexenvelope'] ?? null) !== MessageEnvelope::ENVELOPE_VERSION
96:             || ! ($attributes['data'] ?? null) instanceof stdClass) {
97:             throw InvalidMessage::identity('CloudEvent attributes');
98:         }
99: 
100:         foreach (['id', 'source', 'type', 'time', 'corexscope', 'corexpii'] as $key) {
101:             if (! is_string($attributes[$key] ?? null) || $attributes[$key] === '') {
102:                 throw InvalidMessage::identity("CloudEvent {$key}");
103:             }
104:         }
105: 
106:         foreach (['subject', 'dataschema', 'corexcorrelation', 'corexcausation', 'traceparent', 'tracestate'] as $key) {
107:             if (array_key_exists($key, $attributes) && (! is_string($attributes[$key]) || $attributes[$key] === '')) {
108:                 throw InvalidMessage::identity("CloudEvent {$key}");
109:             }
110:         }
111: 
112:         if (preg_match('/\A([a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*){1,3})\.v([1-9][0-9]*)\z/D', $attributes['type'], $type) !== 1
113:             || strlen($type[2]) > strlen((string) PHP_INT_MAX)
114:             || (strlen($type[2]) === strlen((string) PHP_INT_MAX) && strcmp($type[2], (string) PHP_INT_MAX) > 0)) {
115:             throw InvalidMessage::identity('CloudEvent type');
116:         }
117: 
118:         $contract = $this->contracts->get(name: $type[1], version: (int) $type[2]);
119: 
120:         $schema = self::optionalString($attributes, 'dataschema');
121: 
122:         if ($schema !== null && (! UriReference::isAbsolute($schema) || $schema !== $contract->dataSchema)) {
123:             throw InvalidMessage::identity('CloudEvent data schema');
124:         }
125: 
126:         $pii = PiiClass::tryFrom($attributes['corexpii']);
127: 
128:         if ($pii === null) {
129:             throw InvalidMessage::identity('CloudEvent PII class');
130:         }
131: 
132:         try {
133:             $scopeObject = json_decode($attributes['corexscope'], false, 512, JSON_THROW_ON_ERROR);
134:             $scope = json_decode($attributes['corexscope'], true, 512, JSON_THROW_ON_ERROR);
135:         } catch (JsonException $e) {
136:             throw InvalidMessage::payloadShape($e->getMessage());
137:         }
138: 
139:         if (! $scopeObject instanceof stdClass || ! is_array($scope)) {
140:             throw InvalidMessage::identity('CloudEvent scope');
141:         }
142: 
143:         $metadata = array_intersect_key($attributes, array_flip(['traceparent', 'tracestate']));
144:         self::assertMetadata($metadata);
145: 
146:         /** @var array<string, scalar|null> $scope Object shape verified above. */
147:         /** @var array<string, scalar|null> $metadata String attributes verified above. */
148:         return new MessageEnvelope(
149:             messageId: $attributes['id'], source: $attributes['source'], name: $type[1], contractVersion: (int) $type[2],
150:             occurredAt: self::time($attributes['time']), scope: $scope,
151:             payload: MessagePayload::fromJson(self::json($attributes['data'])), piiClass: $pii,
152:             subject: self::optionalString($attributes, 'subject'), correlationId: self::optionalString($attributes, 'corexcorrelation'),
153:             causationId: self::optionalString($attributes, 'corexcausation'), metadata: $metadata,
154:         );
155:     }
156: 
157:     /** @return array<string, mixed> */
158:     private static function identity(MessageEnvelope $message): array
159:     {
160:         return ['specversion' => '1.0', 'id' => $message->messageId, 'source' => $message->source, 'type' => $message->contract()];
161:     }
162: 
163:     /** @return array<string, mixed> */
164:     private static function content(MessageEnvelope $message): array
165:     {
166:         return ['time' => $message->occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'), 'datacontenttype' => 'application/json'];
167:     }
168: 
169:     /** @param array<string, mixed> $metadata */
170:     private static function assertMetadata(array $metadata): void
171:     {
172:         foreach ($metadata as $key => $value) {
173:             if (! in_array($key, ['traceparent', 'tracestate'], true) || ! is_string($value) || $value === '') {
174:                 throw InvalidMessage::identity('CloudEvent trace metadata');
175:             }
176:         }
177: 
178:         if (isset($metadata['tracestate']) && ! isset($metadata['traceparent'])) {
179:             throw InvalidMessage::identity('CloudEvent traceparent');
180:         }
181:     }
182: 
183:     /** @param array<array-key, mixed> $attributes */
184:     private static function optionalString(array $attributes, string $key): ?string
185:     {
186:         if (! array_key_exists($key, $attributes)) {
187:             return null;
188:         }
189: 
190:         $value = $attributes[$key];
191: 
192:         if (! is_string($value) || $value === '') {
193:             throw InvalidMessage::identity("CloudEvent {$key}");
194:         }
195: 
196:         return $value;
197:     }
198: 
199:     private static function time(string $value): DateTimeImmutable
200:     {
201:         if (preg_match('/\A\d{4}-\d{2}-\d{2}[Tt]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:[Zz]|[+-](?:[01]\d|2[0-3]):[0-5]\d)\z/D', $value) !== 1) {
202:             throw InvalidMessage::identity('CloudEvent time');
203:         }
204: 
205:         try {
206:             $time = new DateTimeImmutable($value);
207:         } catch (Exception) {
208:             throw InvalidMessage::identity('CloudEvent time');
209:         }
210: 
211:         if (DateTimeImmutable::getLastErrors() !== false) {
212:             throw InvalidMessage::identity('CloudEvent time');
213:         }
214: 
215:         return $time;
216:     }
217: 
218:     /**
219:      * @param  array<array-key, mixed>  $values
220:      * @return array<array-key, mixed>
221:      */
222:     private static function canonical(array $values, int $depth = 0): array
223:     {
224:         if ($depth >= MessagePayload::MAX_DEPTH) {
225:             throw InvalidMessage::payloadShape('nesting exceeds eight levels');
226:         }
227: 
228:         foreach ($values as $key => $value) {
229:             if (is_array($value)) {
230:                 $values[$key] = self::canonical(values: $value, depth: $depth + 1);
231:             } elseif ($value !== null && ! is_scalar($value)) {
232:                 throw InvalidMessage::payloadShape('contains an unsupported value');
233:             }
234:         }
235: 
236:         if (! array_is_list($values)) {
237:             ksort($values);
238:         }
239: 
240:         return $values;
241:     }
242: 
243:     /** @param array<array-key, mixed> $data */
244:     private static function objectData(array $data): stdClass
245:     {
246:         foreach (array_keys($data) as $key) {
247:             if (! is_string($key) || $key === '') {
248:                 throw InvalidMessage::payloadShape('external data must be an object');
249:             }
250:         }
251: 
252:         return (object) self::canonical($data);
253:     }
254: 
255:     private static function json(mixed $value): string
256:     {
257:         try {
258:             return json_encode($value, self::JsonFlags);
259:         } catch (JsonException $e) {
260:             throw InvalidMessage::payloadShape($e->getMessage());
261:         }
262:     }
263: }

FULL SOURCE packages/core/src/Messaging/Internal/UriReference.php SHA256 2d16c331884643763135371f93d4b994e21626394b4569c833ea5822215fcdd3
1: <?php
2: 
3: declare(strict_types=1);
4: 
5: namespace CoreX\Messaging\Internal;
6: 
7: /** RFC 3986 generic syntax; not network reachability or scheme policy. */
8: final class UriReference
9: {
10:     private const string Pattern = <<<'REGEX'
11: ~\A(?:(?&uri)|(?&relative))(?:\?(?&query))?(?:\#(?&query))?\z
12: (?(DEFINE)
13:     (?<unreserved> [A-Za-z0-9._\~-] )
14:     (?<subdelims> [!$&'()*+,;=] )
15:     (?<pct> %[0-9A-Fa-f]{2} )
16:     (?<pchar> (?&unreserved)|(?&pct)|(?&subdelims)|[:@] )
17:     (?<segment> (?&pchar)* )
18:     (?<segmentnz> (?&pchar)+ )
19:     (?<segmentnc> (?:(?&unreserved)|(?&pct)|(?&subdelims)|@)+ )
20:     (?<abempty> (?:/(?&segment))* )
21:     (?<absolute> /(?:(?&segmentnz)(?:/(?&segment))*)? )
22:     (?<rootless> (?&segmentnz)(?:/(?&segment))* )
23:     (?<noscheme> (?&segmentnc)(?:/(?&segment))* )
24:     (?<scheme> [A-Za-z][A-Za-z0-9+.-]* )
25:     (?<userinfo> (?:(?&unreserved)|(?&pct)|(?&subdelims)|:)* )
26:     (?<regname> (?:(?&unreserved)|(?&pct)|(?&subdelims))* )
27:     (?<octet> 25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9][0-9]|[0-9] )
28:     (?<ipv4> (?&octet)\.(?&octet)\.(?&octet)\.(?&octet) )
29:     (?<h16> [0-9A-Fa-f]{1,4} )
30:     (?<ls32> (?&h16):(?&h16)|(?&ipv4) )
31:     (?<ipv6>
32:         (?:(?&h16):){6}(?&ls32)
33:       | ::(?:(?&h16):){5}(?&ls32)
34:       | (?&h16)?::(?:(?&h16):){4}(?&ls32)
35:       | (?:(?:(?&h16):){0,1}(?&h16))?::(?:(?&h16):){3}(?&ls32)
36:       | (?:(?:(?&h16):){0,2}(?&h16))?::(?:(?&h16):){2}(?&ls32)
37:       | (?:(?:(?&h16):){0,3}(?&h16))?::(?&h16):(?&ls32)
38:       | (?:(?:(?&h16):){0,4}(?&h16))?::(?&ls32)
39:       | (?:(?:(?&h16):){0,5}(?&h16))?::(?&h16)
40:       | (?:(?:(?&h16):){0,6}(?&h16))?::
41:     )
42:     (?<ipfuture> [vV][0-9A-Fa-f]+\.(?:(?&unreserved)|(?&subdelims)|:)+ )
43:     (?<host> \[(?:(?&ipv6)|(?&ipfuture))\]|(?&regname) )
44:     (?<authority> (?:(?&userinfo)@)?(?&host)(?::[0-9]*)? )
45:     (?<hier> //(?&authority)(?&abempty)|(?&absolute)|(?&rootless)| )
46:     (?<relative> //(?&authority)(?&abempty)|(?&absolute)|(?&noscheme)| )
47:     (?<uri> (?&scheme):(?&hier) )
48:     (?<query> (?:(?&pchar)|[/?])* )
49: )
50: ~xD
51: REGEX;
52: 
53:     public static function isValid(string $value): bool
54:     {
55:         return preg_match(self::Pattern, $value) === 1;
56:     }
57: 
58:     public static function isAbsolute(string $value): bool
59:     {
60:         return self::isValid($value)
61:             && preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:/D', $value) === 1
62:             && ! str_contains($value, '#');
63:     }
64: }

FULL SOURCE packages/core/src/Messaging/Data/MessageEnvelope.php SHA256 8b77a2ded9ef1fd42bba136c9aa47419fd80f7e0eebefd2994c41e2341495de9
1: <?php
2: 
3: declare(strict_types=1);
4: 
5: namespace CoreX\Messaging\Data;
6: 
7: use CoreX\Attributes\Api;
8: use CoreX\Messaging\Contracts\DurablePayload;
9: use CoreX\Messaging\Enums\PiiClass;
10: use CoreX\Messaging\Exceptions\InvalidMessage;
11: use CoreX\Messaging\Internal\UriReference;
12: use DateTimeImmutable;
13: 
14: /**
15:  * Конверт опубликованного сообщения (MSG-003).
16:  *
17:  * `envelopeVersion` — версия формата ЭТОГО конверта; `contractVersion` — мажорная
18:  * версия схемы содержимого. Их смешение — типовая и дорогая ошибка, поэтому это два
19:  * независимых свойства (см. research/P5-design.md §2.2).
20:  *
21:  * Наружу публикуется одна идентичность контракта — contract(), то есть `name.vN`:
22:  * подписка на имя без версии однажды получит следующую мажорную версию (D24).
23:  */
24: #[Api(since: '1.0')]
25: final readonly class MessageEnvelope implements DurablePayload
26: {
27:     public const int ENVELOPE_VERSION = 1;
28: 
29:     /**
30:      * @param  array<string, scalar|null>  $scope  результат DataScopeCodec::encode()
31:      * @param  array<string, scalar|null>  $metadata
32:      */
33:     public function __construct(
34:         public string $messageId,
35:         public string $source,
36:         public string $name,
37:         public int $contractVersion,
38:         public DateTimeImmutable $occurredAt,
39:         public array $scope,
40:         public MessagePayload $payload,
41:         public PiiClass $piiClass,
42:         public ?string $subject = null,
43:         public ?string $correlationId = null,
44:         public ?string $causationId = null,
45:         public array $metadata = [],
46:         public int $envelopeVersion = self::ENVELOPE_VERSION,
47:     ) {
48:         self::assertOpaque(value: $messageId, name: 'message identity');
49:         self::assertOpaque(value: $source, name: 'message source');
50: 
51:         if (! UriReference::isValid($source)) {
52:             throw InvalidMessage::identity('message source');
53:         }
54: 
55:         if ($contractVersion < 1) {
56:             throw InvalidMessage::identity("contract version [{$contractVersion}]");
57:         }
58: 
59:         if ($envelopeVersion !== self::ENVELOPE_VERSION) {
60:             throw InvalidMessage::identity("envelope version [{$envelopeVersion}]");
61:         }
62: 
63:         if ($scope === []) {
64:             throw InvalidMessage::identity('scope: a published message carries a data scope (RUN-002)');
65:         }
66: 
67:         if ($subject !== null) {
68:             self::assertOpaque(value: $subject, name: 'message subject');
69:         }
70: 
71:         if ($correlationId !== null) {
72:             self::assertOpaque(value: $correlationId, name: 'correlation identity');
73:         }
74: 
75:         if ($causationId !== null) {
76:             self::assertOpaque(value: $causationId, name: 'causation identity');
77:         }
78:     }
79: 
80:     /** Опубликованная идентичность контракта: `membership.expired.v1`. */
81:     public function contract(): string
82:     {
83:         return "{$this->name}.v{$this->contractVersion}";
84:     }
85: 
86:     public function withPayload(MessagePayload $payload): self
87:     {
88:         return new self(
89:             messageId: $this->messageId, source: $this->source, name: $this->name,
90:             contractVersion: $this->contractVersion, occurredAt: $this->occurredAt,
91:             scope: $this->scope, payload: $payload, piiClass: $this->piiClass,
92:             subject: $this->subject, correlationId: $this->correlationId,
93:             causationId: $this->causationId, metadata: $this->metadata,
94:             envelopeVersion: $this->envelopeVersion,
95:         );
96:     }
97: 
98:     private static function assertOpaque(string $value, string $name): void
99:     {
100:         if ($value === '' || strlen($value) > 128 || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
101:             throw InvalidMessage::identity($name);
102:         }
103:     }
104: }

FULL SOURCE packages/core/src/Messaging/Data/MessageContract.php SHA256 dc395c054fddc70469b3b3c90ce56f936be403ab02120f6a83c5dc14d5fb265f
1: <?php
2: 
3: declare(strict_types=1);
4: 
5: namespace CoreX\Messaging\Data;
6: 
7: use CoreX\Attributes\Api;
8: use CoreX\Messaging\Enums\MessageClass;
9: use CoreX\Messaging\Enums\PiiClass;
10: use CoreX\Messaging\Exceptions\InvalidMessage;
11: use CoreX\Messaging\Internal\UriReference;
12: 
13: /**
14:  * Объявление опубликованного контракта сообщения.
15:  *
16:  * Существование этой записи — и есть повышение факта до публичного контракта
17:  * (MSG-002). Ни рефлексия, ни подписка, ни имя класса такого повышения не дают.
18:  */
19: #[Api(since: '1.0')]
20: final readonly class MessageContract
21: {
22:     public function __construct(
23:         public string $name,
24:         public int $version,
25:         public MessageClass $class,
26:         public string $owner,
27:         public PiiClass $maxPiiClass,
28:         public string $channel,
29:         public ?string $dataSchema = null,
30:     ) {
31:         self::assertContractName($name);
32: 
33:         if ($dataSchema !== null && ! UriReference::isAbsolute($dataSchema)) {
34:             throw InvalidMessage::identity('contract data schema');
35:         }
36: 
37:         if ($version < 1) {
38:             throw InvalidMessage::identity("contract version [{$version}]");
39:         }
40: 
41:         if ($class === MessageClass::DomainEvent) {
42:             throw InvalidMessage::identity(
43:                 "contract [{$name}]: a domain event is not a published contract (MSG-002)",
44:             );
45:         }
46: 
47:         if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\/[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $owner) !== 1) {
48:             throw InvalidMessage::identity("contract owner [{$owner}]");
49:         }
50: 
51:         if ($channel === '' || strlen($channel) > 120 || preg_match('/[\x00-\x1f\x7f]/', $channel) === 1) {
52:             throw InvalidMessage::identity("contract channel [{$channel}]");
53:         }
54:     }
55: 
56:     /** Опубликованная идентичность контракта: единственная строка, видимая потребителю. */
57:     public function id(): string
58:     {
59:         return "{$this->name}.v{$this->version}";
60:     }
61: 
62:     private static function assertContractName(string $value): void
63:     {
64:         if (str_contains($value, '\\') || str_starts_with($value, 'CoreX')) {
65:             throw InvalidMessage::contractName($value);
66:         }
67: 
68:         if (preg_match('/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*){1,3}\z/D', $value) !== 1) {
69:             throw InvalidMessage::contractName($value);
70:         }
71:     }
72: }

FULL SOURCE packages/webhooks/src/Internal/WebhookFanout.php SHA256 1b1ea2eb19c8f3cbdcc8b8e19088dd9218e184ca716356355e7111541858a550
1: <?php
2: 
3: declare(strict_types=1);
4: 
5: namespace CoreX\Webhooks\Internal;
6: 
7: use CoreX\Messaging\Contracts\CloudEventCodec;
8: use CoreX\Messaging\Contracts\Inbox;
9: use CoreX\Messaging\Data\MessageEnvelope;
10: use CoreX\Operations\Validation\Laravel\JsonCanonical;
11: use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
12: use DateInterval;
13: use Illuminate\Database\Connection;
14: use Psr\Clock\ClockInterface;
15: use RuntimeException;
16: 
17: /** Materializes one delivery per active subscription inside the inbox transaction. */
18: final readonly class WebhookFanout
19: {
20:     public const string Consumer = 'api.webhooks.v1';
21: 
22:     public const int BodyLimit = 20 * 1024;
23: 
24:     /**
25:      * @param  array<string, WebhookPayloadProjector>  $projectors
26:      */
27:     public function __construct(
28:         private Connection $connection,
29:         private Inbox $inbox,
30:         private WebhookTopicCatalogue $catalogue,
31:         private array $projectors,
32:         private ClockInterface $clock,
33:         private CloudEventCodec $codec,
34:     ) {}
35: 
36:     public function accept(MessageEnvelope $message): void
37:     {
38:         $this->connection->transaction(function () use ($message): void {
39:             if (! $this->inbox->claim(self::Consumer, $message->messageId, $message->name)) {
40:                 return;
41:             }
42: 
43:             $topic = $this->catalogue->get($message->name, $message->contractVersion);
44:             $projector = $this->projectors[$topic->projectorService] ?? null;
45: 
46:             if (! $projector instanceof WebhookPayloadProjector) {
47:                 throw new RuntimeException('Webhook projector is not installed.');
48:             }
49: 
50:             $rows = $this->connection->table('sys_api_webhook_subscriptions')
51:                 ->where('status', 'active')
52:                 ->where('topic', $message->name)
53:                 ->where('topic_version', $message->contractVersion)
54:                 ->orderBy('id')
55:                 ->get();
56: 
57:             if ($rows->count() > 100) {
58:                 throw new RuntimeException('Webhook fan-out exceeds 100 matching subscriptions.');
59:             }
60: 
61:             $projected = $projector->project($message, $topic);
62:             $encoded = $this->codec->encodeExternal($message, $projected);
63:             $oversize = strlen($encoded) > self::BodyLimit;
64:             $body = $oversize ? $this->codec->encodeExternal($message, ['error' => 'payload_too_large']) : $encoded;
65:             $now = $this->clock->now();
66: 
67:             foreach ($rows as $row) {
68:                 $this->connection->table('sys_api_webhook_deliveries')->insertOrIgnore([
69:                     'id' => substr(hash('sha256', $row->id.'|'.$message->messageId), 0, 32),
70:                     'subscription_id' => $row->id,
71:                     'subscription_revision' => $row->revision,
72:                     'event_id' => $message->messageId,
73:                     'topic' => $topic->name,
74:                     'topic_version' => $topic->version,
75:                     'major' => $topic->major,
76:                     'endpoint_snapshot' => $row->endpoint_url,
77:                     'scope' => JsonCanonical::encode($message->scope),
78:                     'body' => $body,
79:                     'body_hash' => hash('sha256', $body),
80:                     'status' => $oversize ? 'dead' : 'pending',
81:                     'attempt' => 0,
82:                     'replay_count' => 0,
83:                     'lease_owner' => null,
84:                     'lease_until' => null,
85:                     'next_attempt_at' => $now,
86:                     'created_at' => $now,
87:                     'finished_at' => $oversize ? $now : null,
88:                     'expires_at' => $now->add(new DateInterval('P7D')),
89:                     'last_error' => $oversize ? 'payload_too_large' : null,
90:                 ]);
91:             }
92:         });
93:     }
94: }

FULL SOURCE packages/webhooks/src/Internal/WebhookDeliveryWorker.php SHA256 a5f08b3ed0a7f29bf57ffd20338101a6ae73f23a88330391119f3e8afe3a4844
1: <?php
2: 
3: declare(strict_types=1);
4: 
5: namespace CoreX\Webhooks\Internal;
6: 
7: use CoreX\Data\Data\DataScope;
8: use CoreX\Operations\Errors\Enums\OperationsErrorCode;
9: use CoreX\Operations\Errors\Exceptions\ApiError;
10: use CoreX\Webhooks\Contracts\WebhookHttpTransport;
11: use CoreX\Webhooks\Contracts\WebhookSigner;
12: use CoreX\Webhooks\Data\WebhookDelivery;
13: use CoreX\Webhooks\Data\WebhookHttpResult;
14: use InvalidArgumentException;
15: use Psr\Clock\ClockInterface;
16: use RuntimeException;
17: use Throwable;
18: 
19: /** @internal Performs one network delivery after the durable claim transaction commits. */
20: final readonly class WebhookDeliveryWorker
21: {
22:     public function __construct(
23:         private SqlWebhookDeliveryStore $store,
24:         private WebhookSigner $signer,
25:         private WebhookUrlPolicy $urlPolicy,
26:         private WebhookHttpTransport $transport,
27:         private WebhookTopicCatalogue $topics,
28:         private ClockInterface $clock,
29:     ) {}
30: 
31:     public function workOnce(DataScope $scope, string $worker): ?WebhookDelivery
32:     {
33:         $delivery = $this->store->claim($scope, $worker);
34: 
35:         if ($delivery === null) {
36:             return null;
37:         }
38: 
39:         $keyRefs = $this->store->signingKeyRefs($scope, $delivery);
40: 
41:         if ($keyRefs === null) {
42:             return $this->store->find($scope, $delivery->id) ?? $delivery;
43:         }
44: 
45:         try {
46:             $topic = $this->topics->get($delivery->topic, $delivery->topicVersion);
47: 
48:             if ($topic->major !== $delivery->major) {
49:                 throw new ApiError(OperationsErrorCode::UnknownMessageContract, 422, 'Webhook topic major changed.');
50:             }
51:         } catch (ApiError) {
52:             return $this->finish($scope, $delivery, new WebhookHttpResult(
53:                 status: null,
54:                 retryAfter: null,
55:                 errorCode: 'topic_unavailable',
56:                 durationMs: 0,
57:             ));
58:         }
59: 
60:         try {
61:             $signed = $this->signer->sign(
62:                 deliveryId: $delivery->id,
63:                 timestamp: $this->clock->now()->getTimestamp(),
64:                 rawBody: $delivery->body,
65:                 secretRefs: $keyRefs,
66:             );
67:         } catch (Throwable) {
68:             return $this->finish($scope, $delivery, new WebhookHttpResult(
69:                 status: null,
70:                 retryAfter: null,
71:                 errorCode: 'signing_unavailable',
72:                 durationMs: 0,
73:             ));
74:         }
75: 
76:         try {
77:             $request = $this->urlPolicy->validate(
78:                 url: $delivery->endpointSnapshot,
79:                 body: $delivery->body,
80:                 signedHeaders: $signed,
81:             );
82:         } catch (InvalidArgumentException) {
83:             return $this->finish($scope, $delivery, new WebhookHttpResult(
84:                 status: null,
85:                 retryAfter: null,
86:                 errorCode: 'webhook_url_forbidden',
87:                 durationMs: 0,
88:             ));
89:         } catch (RuntimeException) {
90:             return $this->finish($scope, $delivery, new WebhookHttpResult(
91:                 status: null,
92:                 retryAfter: null,
93:                 errorCode: 'dns_unavailable',
94:                 durationMs: 0,
95:             ));
96:         }
97: 
98:         try {
99:             $result = $this->transport->send($request);
100:         } catch (Throwable) {
101:             $result = new WebhookHttpResult(
102:                 status: null,
103:                 retryAfter: null,
104:                 errorCode: 'network_failure',
105:                 durationMs: 0,
106:             );
107:         }
108: 
109:         return $this->finish($scope, $delivery, $result);
110:     }
111: 
112:     private function finish(
113:         DataScope $scope,
114:         WebhookDelivery $delivery,
115:         WebhookHttpResult $result,
116:     ): WebhookDelivery {
117:         $this->store->finish($delivery, $result);
118: 
119:         return $this->store->find($scope, $delivery->id) ?? $delivery;
120:     }
121: }

FULL SOURCE packages/webhooks/src/Internal/HmacWebhookSigner.php SHA256 2d08ef4113507d9f36df0204e7f0afd9192d129387a01c0b78da82782db0b6d6
1: <?php
2: 
3: declare(strict_types=1);
4: 
5: namespace CoreX\Webhooks\Internal;
6: 
7: use CoreX\Config\Data\SecretRef;
8: use CoreX\Webhooks\Contracts\WebhookSigner;
9: use CoreX\Webhooks\Data\WebhookSignedHeaders;
10: use InvalidArgumentException;
11: 
12: final readonly class HmacWebhookSigner implements WebhookSigner
13: {
14:     private const SECRET_PREFIX = 'whsec_';
15: 
16:     private const SECRET_BYTES = 32;
17: 
18:     public function __construct(
19:         private WebhookKeyRing $keyRing,
20:     ) {}
21: 
22:     public function sign(
23:         string $deliveryId,
24:         int $timestamp,
25:         string $rawBody,
26:         array $secretRefs,
27:     ): WebhookSignedHeaders {
28:         $content = "{$deliveryId}.{$timestamp}.{$rawBody}";
29:         $keys = $this->keyRing->resolveForSigning(
30:             secretRefs: $secretRefs,
31:             timestamp: $timestamp,
32:         );
33:         $signatures = [];
34: 
35:         foreach ($keys as $secret) {
36:             $bytes = $this->decodeSecret($secret);
37:             $digest = hash_hmac(
38:                 algo: 'sha256',
39:                 data: $content,
40:                 key: $bytes,
41:                 binary: true,
42:             );
43:             $signatures[] = 'v1,'.base64_encode($digest);
44:         }
45: 
46:         return new WebhookSignedHeaders(
47:             id: $deliveryId,
48:             timestamp: $timestamp,
49:             signatures: $signatures,
50:         );
51:     }
52: 
53:     private function decodeSecret(SecretRef $secret): string
54:     {
55:         $wire = $secret->reveal();
56: 
57:         if (! str_starts_with($wire, self::SECRET_PREFIX)) {
58:             throw new InvalidArgumentException('Webhook secret has an invalid format.');
59:         }
60: 
61:         $encoded = substr($wire, strlen(self::SECRET_PREFIX));
62:         $bytes = base64_decode(string: $encoded, strict: true);
63: 
64:         if ($bytes === false
65:             || strlen($bytes) !== self::SECRET_BYTES
66:             || ! hash_equals(base64_encode($bytes), $encoded)) {
67:             throw new InvalidArgumentException('Webhook secret has an invalid format.');
68:         }
69: 
70:         return $bytes;
71:     }
72: }

FULL SOURCE packages/core/tests/Unit/CloudEventCodecTest.php SHA256 903b07b2f63c54351a70e0b36046cc55382403c2973be56e2393e47b4b2ff61b
1: <?php
2: 
3: declare(strict_types=1);
4: 
5: use CoreX\Messaging\Data\MessageContract;
6: use CoreX\Messaging\Data\MessageEnvelope;
7: use CoreX\Messaging\Data\MessagePayload;
8: use CoreX\Messaging\Enums\MessageClass;
9: use CoreX\Messaging\Enums\PiiClass;
10: use CoreX\Messaging\Exceptions\InvalidMessage;
11: use CoreX\Messaging\Exceptions\UnknownMessageContract;
12: use CoreX\Messaging\Internal\ArrayMessageContractRegistry;
13: use CoreX\Messaging\Internal\JsonCloudEventCodec;
14: 
15: function cloudEventCodec(?string $schema = 'https://schemas.example.test/customfields.attribute.changed/v1.json'): JsonCloudEventCodec
16: {
17:     return new JsonCloudEventCodec(new ArrayMessageContractRegistry([
18:         new MessageContract('customfields.attribute.changed', 1, MessageClass::IntegrationEvent, 'axiomasoft/corex-custom-fields', PiiClass::Personal, 'events', $schema),
19:     ]));
20: }
21: 
22: function cloudEventMessage(bool $optional = true): MessageEnvelope
23: {
24:     return new MessageEnvelope(
25:         messageId: '01J9Z6Q4A7', source: 'axiomasoft/corex-custom-fields', name: 'customfields.attribute.changed', contractVersion: 1,
26:         occurredAt: new DateTimeImmutable('2026-10-03T08:00:00.123456Z'), scope: ['workspace' => 'w-7', 'account' => 'a-1'],
27:         payload: new MessagePayload(['attribute' => 'color']), piiClass: PiiClass::None, subject: $optional ? 'attr-42' : null,
28:         correlationId: $optional ? 'c-1' : null,
29:         metadata: $optional ? ['traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'] : [],
30:     );
31: }
32: 
33: it('encodes the byte exact CloudEvents example and round trips', function (): void {
34:     $codec = cloudEventCodec();
35:     $expected = '{"specversion":"1.0","id":"01J9Z6Q4A7","source":"axiomasoft/corex-custom-fields","type":"customfields.attribute.changed.v1","subject":"attr-42","time":"2026-10-03T08:00:00.123456Z","datacontenttype":"application/json","dataschema":"https://schemas.example.test/customfields.attribute.changed/v1.json","corexscope":"{\\"account\\":\\"a-1\\",\\"workspace\\":\\"w-7\\"}","corexpii":"none","corexenvelope":1,"corexcorrelation":"c-1","traceparent":"00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01","data":{"attribute":"color"}}';
36:     expect($codec->encode(cloudEventMessage()))->toBe($expected)
37:         ->and($codec->decode($expected))->toEqual(cloudEventMessage());
38:     $plain = cloudEventMessage(false);
39:     $plainCodec = cloudEventCodec(null);
40:     expect($plainCodec->decode($plainCodec->encode($plain)))->toEqual($plain);
41:     $empty = $plain->withPayload(MessagePayload::empty());
42:     expect(json_decode($plainCodec->encode($empty))->data)->toBeInstanceOf(stdClass::class)
43:         ->and($plainCodec->decode($plainCodec->encode($empty)))->toEqual($empty);
44: });
45: 
46: it('external CloudEvents contains only public attributes and projected data', function (): void {
47:     $message = cloudEventMessage()->withPayload(new MessagePayload(['id' => 'attr-42', 'secret' => 'hidden']));
48:     $wire = cloudEventCodec()->encodeExternal($message, ['id' => 'attr-42']);
49:     expect($wire)->toBe('{"specversion":"1.0","id":"01J9Z6Q4A7","source":"axiomasoft/corex-custom-fields","type":"customfields.attribute.changed.v1","time":"2026-10-03T08:00:00.123456Z","datacontenttype":"application/json","data":{"id":"attr-42"}}')
50:         ->and($wire)->not->toContain('hidden', 'dataschema', 'corexscope', 'traceparent', 'subject');
51:     expect(json_decode(cloudEventCodec()->encode($message), true)['dataschema'])->toBe('https://schemas.example.test/customfields.attribute.changed/v1.json');
52: });
53: 
54: it('rejects malformed CloudEvents', function (array $change): void {
55:     $codec = cloudEventCodec();
56:     $event = json_decode($codec->encode(cloudEventMessage()), true, flags: JSON_THROW_ON_ERROR);
57:     $codec->decode(json_encode(array_replace($event, $change), JSON_THROW_ON_ERROR));
58: })->with([
59:     'old spec' => [['specversion' => '0.3']], 'unknown' => [['foo' => 'x']], 'base64' => [['data_base64' => 'eA==']],
60:     'unversioned' => [['type' => 'customfields.attribute.changed']], 'zoneless' => [['time' => '2026-10-03T08:00:00']],
61:     'scope list' => [['corexscope' => '[]']], 'bad date' => [['time' => '2026-02-30T08:00:00Z']],
62:     'invalid pii' => [['corexpii' => 'secret']], 'string envelope' => [['corexenvelope' => '1']],
63:     'data list' => [['data' => [1, 2]]], 'null optional' => [['subject' => null]], 'invalid trace' => [['traceparent' => []]],
64: ])->throws(InvalidMessage::class);
65: 
66: it('rejects broken JSON and non object events', function (string $wire): void {
67:     cloudEventCodec()->decode($wire);
68: })->with(['{', '[]', 'null', '{"specversion":"1.0"}'])->throws(InvalidMessage::class);
69: 
70: it('rejects unknown metadata and tracestate without traceparent', function (array $metadata): void {
71:     $m = cloudEventMessage();
72:     $bad = new MessageEnvelope($m->messageId, $m->source, $m->name, 1, $m->occurredAt, $m->scope, $m->payload, $m->piiClass, metadata: $metadata);
73:     cloudEventCodec()->encode($bad);
74: })->with([[['x-tenant' => 'a']], [['tracestate' => 'vendor=value']], [['traceparent' => null]]])->throws(InvalidMessage::class);
75: 
76: it('rejects undeclared contracts', function (): void {
77:     (new JsonCloudEventCodec(new ArrayMessageContractRegistry([])))->encode(cloudEventMessage());
78: })->throws(UnknownMessageContract::class);
79: 
80: it('accepts URI references in source', function (string $source): void {
81:     $m = cloudEventMessage();
82:     expect(new MessageEnvelope('id', $source, $m->name, 1, $m->occurredAt, $m->scope, $m->payload, $m->piiClass))->source->toBe($source);
83: })->with(['corex/relay', 'axiomasoft/corex-custom-fields', 'fixture-address/1', './1:foo', '/a/b', '../a', '?q=a%20b', '#fragment', 'urn:corex:source', 'https://example.test/a%20b?q=x#f', 'https://[::1]/', 'https://[2001:db8::1]:443/a', 'https://[::ffff:192.0.2.1]/', 'https://[v1.a:b]/']);
84: 
85: it('rejects malformed source URI references', function (string $source): void {
86:     $m = cloudEventMessage();
87:     new MessageEnvelope('id', $source, $m->name, 1, $m->occurredAt, $m->scope, $m->payload, $m->piiClass);
88: })->with(['', str_repeat('x', 129), 'has space', "a\n", 'источник', '%', '%zz', 'a%2', '1:foo', 'http://[not-ip]/', 'https://[::1', 'https://[1::2::3]/', 'https://host:port/', 'https://host/a[b]', 'https://host/#a#b'])->throws(InvalidMessage::class);
89: 
90: it('accepts absolute schema URIs', function (string $schema): void {
91:     expect(cloudEventCodec($schema)->encode(cloudEventMessage()))->toContain('"dataschema":"'.$schema.'"');
92: })->with(['urn:corex:schema:v1', 'https://schemas.example.test/v1.json']);
93: 
94: it('rejects invalid schema URIs', function (string $schema): void {
95:     cloudEventCodec($schema);
96: })->with(['relative/path', 'https://host/%zz', 'https://[bad]/', 'https://host/schema#fragment'])->throws(InvalidMessage::class);
97: 
98: it('bounds external JSON trees and rejects non JSON values', function (): void {
99:     $data = ['value' => 'leaf'];
100:     for ($depth = 0; $depth < 8; $depth++) {
101:         $data = ['nested' => $data];
102:     }
103:     cloudEventCodec()->encodeExternal(cloudEventMessage(), $data);
104: })->throws(InvalidMessage::class);

FULL SOURCE packages/webhooks/tests/Feature/WebhookFanoutTest.php SHA256 a77e2a67982d033e2809d90dc0b7545eaf7ef6cc5749793cd869bf697e5bc5e0
1: <?php
2: 
3: declare(strict_types=1);
4: 
5: namespace CoreX\Webhooks\Tests\Feature;
6: 
7: use CoreX\Config\Data\CoreOptions;
8: use CoreX\Config\Data\SecretRef;
9: use CoreX\Context\Data\AccountRef;
10: use CoreX\CoreServiceProvider;
11: use CoreX\Messaging\Contracts\DurableMessagePublisher;
12: use CoreX\Messaging\Contracts\MessagePublisher;
13: use CoreX\Messaging\Data\MessageContract;
14: use CoreX\Messaging\Data\MessageEnvelope;
15: use CoreX\Messaging\Data\MessagePayload;
16: use CoreX\Messaging\Enums\MessageClass;
17: use CoreX\Messaging\Enums\PiiClass;
18: use CoreX\Messaging\Internal\JsonCloudEventCodec;
19: use CoreX\Messaging\Testing\MessagingFixtures;
20: use CoreX\Operations\Laravel\ApiSchemaRegistrar;
21: use CoreX\Operations\Testing\ContextFixture;
22: use CoreX\Operations\Validation\Laravel\JsonCanonical;
23: use CoreX\Time\Testing\FrozenClock;
24: use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
25: use CoreX\Webhooks\Data\WebhookSubscription;
26: use CoreX\Webhooks\Data\WebhookTopic;
27: use CoreX\Webhooks\Internal\HmacWebhookSigner;
28: use CoreX\Webhooks\Internal\WebhookFanout;
29: use CoreX\Webhooks\Internal\WebhookKeyRing;
30: use CoreX\Webhooks\Internal\WebhookMessagePublisher;
31: use CoreX\Webhooks\Internal\WebhookTopicCatalogue;
32: use CoreX\Webhooks\Tests\TestCase;
33: use DateTimeImmutable;
34: use Illuminate\Database\Schema\Blueprint;
35: use Illuminate\Foundation\Application;
36: use Illuminate\Support\Facades\DB;
37: use PHPUnit\Framework\Attributes\Group;
38: use RuntimeException;
39: use TypeError;
40: 
41: final class WebhookFanoutTest extends TestCase
42: {
43:     private FrozenClock $clock;
44: 
45:     protected function setUp(): void
46:     {
47:         parent::setUp();
48:         $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-25T12:00:00.000000+00:00'));
49:         $this->configure('api_budget_stub');
50:         $this->configure('api_tenant');
51:         $this->selectSchema('api_tenant');
52:         $migration = require __DIR__.'/../../database/migrations/tenant/create_sys_api_webhook_tables.php';
53:         $migration->up();
54:         $this->inbox();
55:     }
56: 
57:     /** @param Application $app */
58:     protected function getPackageProviders($app): array
59:     {
60:         return [CoreServiceProvider::class, ...parent::getPackageProviders($app)];
61:     }
62: 
63:     public function test_projection_failure_rolls_back_inbox_and_delivery_together(): void
64:     {
65:         $this->subscribe('sub-1');
66:         $publisher = $this->publisher(new ThrowingProjector);
67: 
68:         try {
69:             $publisher->publish($this->envelope('11111111-1111-4111-8111-111111111111'));
70:             self::fail('Projection failure was committed.');
71:         } catch (RuntimeException) {
72:             self::assertSame(0, DB::connection('api_tenant')->table(app(CoreOptions::class)->inboxMessagesTable)->count());
73:             self::assertSame(0, DB::connection('api_tenant')->table('sys_api_webhook_deliveries')->count());
74:         }
75:     }
76: 
77:     public function test_duplicate_event_creates_one_delivery_and_forwards_the_existing_publisher(): void
78:     {
79:         $this->subscribe('sub-1');
80:         $forwarded = 0;
81:         $inner = new class($forwarded) implements DurableMessagePublisher
82:         {
83:             public function __construct(private int &$count) {}
84: 
85:             public function publish(MessageEnvelope $message): void
86:             {
87:                 $this->count++;
88:             }
89:         };
90:         $publisher = $this->publisher(new ExportProjector, $inner);
91:         $envelope = $this->envelope('22222222-2222-4222-8222-222222222222');
92:         $publisher->publish($envelope);
93:         $publisher->publish($envelope);
94:         $this->subscribe('sub-later');
95:         $publisher->publish($envelope);
96: 
97:         $rows = DB::connection('api_tenant')->table('sys_api_webhook_deliveries')->get();
98: 
99:         self::assertCount(1, $rows);
100:         self::assertSame($envelope->messageId, $rows[0]->event_id);
101:         self::assertNotSame($rows[0]->event_id, $rows[0]->id);
102:         self::assertSame(hash('sha256', (string) $rows[0]->body), $rows[0]->body_hash);
103:         self::assertSame('https://hooks.example/receiver', $rows[0]->endpoint_snapshot);
104:         self::assertSame(3, $forwarded);
105:         self::assertStringNotContainsString('hidden', (string) $rows[0]->body);
106:         $event = json_decode((string) $rows[0]->body, true, flags: JSON_THROW_ON_ERROR);
107:         self::assertSame(['specversion', 'id', 'source', 'type', 'time', 'datacontenttype', 'data'], array_keys($event));
108:         self::assertSame('1.0', $event['specversion']);
109:         self::assertSame('orders.placed.v1', $event['type']);
110:         self::assertSame(['id' => 'order-1'], $event['data']);
111:         self::assertArrayNotHasKey('dataschema', $event);
112:         $keys = new WebhookKeyRing;
113:         $keys->provision('current', new SecretRef('whsec_'.base64_encode(str_repeat('s', 32))), 0);
114:         $signer = new HmacWebhookSigner($keys);
115:         $signed = $signer->sign((string) $rows[0]->id, 1790337600, (string) $rows[0]->body, ['current']);
116:         self::assertSame(['v1,'.base64_encode(hash_hmac('sha256', $rows[0]->id.'.1790337600.'.$rows[0]->body, str_repeat('s', 32), true))], $signed->signatures);
117:     }
118: 
119:     public function test_oversize_payload_is_dead_and_a_lossy_publisher_is_rejected(): void
120:     {
121:         $this->subscribe('sub-1');
122:         $this->publisher(new HugeProjector)->publish($this->envelope('33333333-3333-4333-8333-333333333333'));
123:         $row = DB::connection('api_tenant')->table('sys_api_webhook_deliveries')->first();
124: 
125:         self::assertNotNull($row);
126:         self::assertSame('dead', $row->status);
127:         self::assertSame('payload_too_large', $row->last_error);
128:         self::assertLessThan(WebhookFanout::BodyLimit, strlen((string) $row->body));
129: 
130:         $this->expectException(TypeError::class);
131:         new WebhookMessagePublisher(new class implements MessagePublisher
132:         {
133:             public function publish(MessageEnvelope $message): void {}
134:         }, $this->fanout(new ExportProjector));
135:     }
136: 
137:     #[Group('pg')]
138:     public function test_postgresql_inbox_and_delivery_roll_back_together(): void
139:     {
140:         $database = getenv('PGSQL_DATABASE') ?: '';
141: 
142:         if (preg_match('/\A[a-z0-9_]+_test\z/D', $database) !== 1) {
143:             self::markTestSkipped('PGSQL_DATABASE must name an isolated *_test database.');
144:         }
145: 
146:         $base = [
147:             'driver' => 'pgsql',
148:             'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
149:             'port' => getenv('PGSQL_PORT') ?: '5434',
150:             'database' => $database,
151:             'username' => getenv('PGSQL_USERNAME') ?: 'corex',
152:             'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
153:             'charset' => 'utf8',
154:             'prefix' => '',
155:         ];
156:         $this->app['config']->set('database.connections.api_pg_root', $base + ['search_path' => 'public']);
157:         $this->app['config']->set('database.connections.api_pg_tenant', $base + ['search_path' => 'api_p1412_tenant_test']);
158:         DB::purge('api_pg_root');
159:         DB::purge('api_pg_tenant');
160:         $root = DB::connection('api_pg_root');
161:         self::assertSame($database, $root->getDatabaseName());
162:         $root->statement('DROP SCHEMA IF EXISTS api_p1412_tenant_test CASCADE');
163:         $root->statement('CREATE SCHEMA api_p1412_tenant_test');
164: 
165:         try {
166:             $this->selectSchema('api_pg_tenant');
167:             $migration = require __DIR__.'/../../database/migrations/tenant/create_sys_api_webhook_tables.php';
168:             $migration->up();
169:             $this->inbox('api_pg_tenant');
170:             $connection = DB::connection('api_pg_tenant');
171:             $this->subscribe('sub-pg', $connection->getName());
172:             $fanout = $this->fanout(new ThrowingProjector, 'api_pg_tenant');
173: 
174:             try {
175:                 $fanout->accept($this->envelope('44444444-4444-4444-8444-444444444444'));
176:                 self::fail('PostgreSQL projection failure was committed.');
177:             } catch (RuntimeException) {
178:                 self::assertSame(0, $connection->table(app(CoreOptions::class)->inboxMessagesTable)->count());
179:                 self::assertSame(0, $connection->table('sys_api_webhook_deliveries')->count());
180:             }
181:         } finally {
182:             $root->statement('DROP SCHEMA IF EXISTS api_p1412_tenant_test CASCADE');
183:         }
184:     }
185: 
186:     private function publisher(WebhookPayloadProjector $projector, ?DurableMessagePublisher $inner = null): WebhookMessagePublisher
187:     {
188:         $inner ??= new class implements DurableMessagePublisher
189:         {
190:             public function publish(MessageEnvelope $message): void {}
191:         };
192: 
193:         return new WebhookMessagePublisher($inner, $this->fanout($projector));
194:     }
195: 
196:     private function fanout(WebhookPayloadProjector $projector, string $connection = 'api_tenant'): WebhookFanout
197:     {
198:         $db = DB::connection($connection);
199:         $contexts = new ContextFixture;
200:         $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
201:         $schema = ['id' => 'string'];
202:         $topic = new WebhookTopic('orders.placed', 1, 'v1', $schema, hash('sha256', JsonCanonical::encode($schema)), 'personal', 'orders.placed');
203:         $contracts = MessagingFixtures::contracts([
204:             new MessageContract('orders.placed', 1, MessageClass::IntegrationEvent, 'orders/api', PiiClass::Personal, 'webhooks', 'https://schemas.example.test/orders/v1.json'),
205:         ]);
206:         $catalogue = new WebhookTopicCatalogue($contracts, [$topic], ['orders.placed' => $projector]);
207: 
208:         return new WebhookFanout($db, MessagingFixtures::inbox($db, $this->clock), $catalogue, ['orders.placed' => $projector], $this->clock, new JsonCloudEventCodec($contracts));
209:     }
210: 
211:     private function subscribe(string $id, string $connection = 'api_tenant'): void
212:     {
213:         $now = $this->clock->now();
214:         $subscription = new WebhookSubscription($id, 0, 'v1', 'orders.placed', 1, 'https://hooks.example/receiver', 'active', 'personal', ['current'], $now, $now);
215:         DB::connection($connection)->table('sys_api_webhook_subscriptions')->insert([
216:             'id' => $subscription->id,
217:             'revision' => 0,
218:             'major' => $subscription->major,
219:             'topic' => $subscription->topic,
220:             'topic_version' => $subscription->topicVersion,
221:             'endpoint_url' => $subscription->endpointUrl,
222:             'status' => 'active',
223:             'max_pii_class' => $subscription->maxPiiClass,
224:             'key_refs' => '["current"]',
225:             'created_at' => $now,
226:             'updated_at' => $now,
227:         ]);
228:     }
229: 
230:     private function envelope(string $id): MessageEnvelope
231:     {
232:         return new MessageEnvelope(
233:             messageId: $id,
234:             source: 'orders',
235:             name: 'orders.placed',
236:             contractVersion: 1,
237:             occurredAt: new DateTimeImmutable('2026-09-25T12:00:00+00:00'),
238:             scope: ['account_id' => 'account-1'],
239:             payload: new MessagePayload(['id' => 'order-1', 'secret' => 'hidden']),
240:             piiClass: PiiClass::Personal,
241:             correlationId: 'correlation-1',
242:         );
243:     }
244: 
245:     private function inbox(string $connection = 'api_tenant'): void
246:     {
247:         DB::connection($connection)->getSchemaBuilder()->create(app(CoreOptions::class)->inboxMessagesTable, function (Blueprint $table): void {
248:             $table->string('consumer', 120);
249:             $table->uuid('message_id');
250:             $table->string('name', 160);
251:             $table->timestampTz('processed_at', precision: 6);
252:             $table->primary(['consumer', 'message_id']);
253:         });
254:     }
255: 
256:     private function configure(string $name): void
257:     {
258:         $this->app['config']->set('database.connections.'.$name, [
259:             'driver' => 'sqlite',
260:             'database' => ':memory:',
261:             'prefix' => '',
262:             'foreign_key_constraints' => true,
263:         ]);
264:         DB::purge($name);
265:     }
266: 
267:     private function selectSchema(string $tenant): void
268:     {
269:         $this->app->forgetInstance(ApiSchemaRegistrar::class);
270:         $driver = (string) $this->app['config']->get('database.connections.'.$tenant.'.driver');
271:         $database = (string) $this->app['config']->get('database.connections.'.$tenant.'.database');
272:         $this->app['config']->set('corex-operations.schema', [
273:             'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
274:             'tenant' => ['connection' => $tenant, 'driver' => $driver, 'database' => $database],
275:         ]);
276:     }
277: }
278: 
279: final class ExportProjector implements WebhookPayloadProjector
280: {
281:     public function project(MessageEnvelope $message, WebhookTopic $topic): array
282:     {
283:         return ['id' => 'order-1'];
284:     }
285: }
286: 
287: final class ThrowingProjector implements WebhookPayloadProjector
288: {
289:     public function project(MessageEnvelope $message, WebhookTopic $topic): array
290:     {
291:         throw new RuntimeException('projection failed');
292:     }
293: }
294: 
295: final class HugeProjector implements WebhookPayloadProjector
296: {
297:     public function project(MessageEnvelope $message, WebhookTopic $topic): array
298:     {
299:         return ['id' => str_repeat('x', WebhookFanout::BodyLimit)];
300:     }
301: }
FINAL PROJECTOR CONTRACT
<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Messaging\Contracts\CloudEventCodec;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Webhooks\Data\WebhookTopic;

/**
 * Installed field allow-list projector: returns only public data for the external CloudEvent.
 *
 * @phpstan-import-type EventData from CloudEventCodec
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WebhookPayloadProjector
{
    /** @return EventData Projected JSON object; never an envelope or internal metadata. */
    public function project(MessageEnvelope $message, WebhookTopic $topic): array;
}

FIXTURE REPAIR PROOF
{
    "before_count": 120,
    "after_count": 136,
    "added": [
        "operations.input_envelope_invalid",
        "operations.undeclared_fields",
        "operations.unsupported_media_type",
        "tools.approval_invalid",
        "tools.approval_required",
        "tools.forbidden",
        "tools.idempotency_conflict",
        "tools.input_invalid",
        "tools.internal_error",
        "tools.output_contract_failed",
        "tools.rate_limited",
        "tools.schema_limit",
        "tools.schema_unavailable",
        "tools.tool_revision_conflict",
        "tools.tool_unavailable",
        "tools.unknown_effect"
    ]
}

Final acceptance completed: root1083+17, PG242, core300, webhooks102incl6PG, focused73, all package/root PHPStan0/ceilingsOK, strictAPI0, two stable generations, full BC cohort and final core/webhooks BC0, Golden4, committed contract guards0, antiweakening0, exact clean final candidate lint0. The only old untracked-PHP lint failures remain foreign baseline. Final typed projector annotation imports the codec's EventData alias; only PHPDoc/native unchanged. Alias limits use the already adopted eight-level JSON payload boundary. All 16 added error-fixture definitions already existed at saved base; all old rows and other fixture sections remain byte-semantic equal. No scanner or acceptance floor was changed. Product code/schema/error implementation outside declared wire outcome unchanged.

## Assistant

Verdict: GREEN

Material defects: none.

Reviewed the embedded P06.3 product sources for `bf557c36ca9cdcec93930e22f71fb22b6a3c8674..5fff36ed801c0f42f803cea85ebdc3fe33a2ddf8`, including `JsonCloudEventCodec.php` (`0d5f3201…`), `UriReference.php` (`2d16c331…`), `MessageEnvelope.php` (`8b77a2de…`), `MessageContract.php` (`dc395c05…`), `WebhookFanout.php` (`1b1ea2eb…`), and the codec and fan-out tests.

- Internal encode order matches examples §6, including canonical `corexscope` (`account` before `workspace`), UTC `Y-m-d\TH:i:s.u\Z`, and omission of null `subject`, `dataschema`, correlation, causation, and trace keys. `decode(encode($m))` rebuilds the envelope, and optional-field omission is covered.
- `encodeExternal` emits exactly `specversion`, `id`, `source`, `type`, `time`, `datacontenttype`, `data`. With `MessageContract::$dataSchema` set and a projector that drops `secret`, the external bytes keep `{"id":"attr-42"}` and carry no `dataschema`, `subject`, trace, or `corex*` attributes. Internal encode still writes `dataschema`.
- Decode is a closed attribute set: spec `1.0`, `application/json`, integer `corexenvelope` `1`, object `data`, versioned `type`, absolute `dataschema` equal to the registered contract, scalar extensions only, and `tracestate` only with `traceparent`.
- `UriReference` is the dossier RFC 3986 grammar. Source is a non-empty reference of at most 128 bytes; `dataSchema` is absolute and fragment-free. The §6c accept and reject sets are the ones in `CloudEventCodecTest`.
- `MessagingRegistrar` scopes `CloudEventCodec` to `JsonCloudEventCodec`; `WebhookBindingsRegistrar` injects it into `WebhookFanout`.
- Fan-out stores one `encodeExternal` string, hashes that string, and signs `delivery.body`. An oversize body is replaced by one short external event and marked `dead` with `payload_too_large`. Curl’s `Content-Type` is `application/cloudevents+json`.
- `WebhookPayloadProjector::project` returns the public `EventData` object. The native `array` parameter is unchanged; the PHPDoc is the eight-level JSON tree (`null|bool|int|float|string` through `EventLevel7`), and `objectData` enforces that depth.

This is a static verdict on those bytes. Runtime acceptance is the packet’s reported result, not a suite I re-ran.