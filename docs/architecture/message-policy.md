# Messaging policy

This policy gives the formats and procedures for constitutional articles `MSG-001` through `MSG-008`. [Constitution](constitution.md)

## Message classes

| Class | Meaning | Recipient | Handlers | Delivery | Name |
|:--|:--|:--|:--|:--|:--|
| Command | Intent to change state | One owner | Exactly one | Synchronous or queued, with no implied success | Imperative verb, e.g. `EnableModule` |
| Domain event | A fact within one context | Internal subscribers | Zero or more | Usually in-process in the same unit of work | Past tense, e.g. `ModuleEnabled` |
| Integration event | A published fact with a compatibility contract | Independent consumers across a boundary | Zero or more | Asynchronous, at least once | Past tense with a stable, versioned name |

A framework event needs an explicit declaration to become an integration event. Its published name and schema then carry a compatibility guarantee.

## Published message envelope

The wire format is **CloudEvents 1.0 structured JSON**, with media type `application/cloudevents+json`. The public `CloudEventCodec` encodes the internal and external profiles and strictly decodes the internal profile. Key order is fixed. Absent optional attributes are omitted.

| Attribute | Source or format |
|:--|:--|
| `specversion` | `"1.0"` |
| `id` | `messageId`, a nonempty string of at most 128 bytes |
| `source` | Nonempty RFC 3986 URI-reference, at most 128 bytes |
| `type` | The declared `name.vN` |
| `subject` | Canonical JSON string containing a `RecordRef` or `EntityHandle` |
| `time` | `occurredAt` in UTC RFC 3339 with six fractional-second digits |
| `datacontenttype` | `application/json` |
| `dataschema` | Optional `MessageContract::$dataSchema`, an absolute URI without a fragment |
| `corexscope` | Canonical JSON string of the scope object with keys sorted recursively |
| `corexpii` | `none`, `pseudonymous` or `personal` |
| `corexenvelope` | Integer `envelopeVersion`, currently 2 |
| `corexproof` | HMAC issued when appending to the outbox and stored with the fact |
| `corexcorrelation`, `corexcausation` | Correlation is required, causation is optional. Each identity is at most 128 bytes. |
| `traceparent`, `tracestate` | The only supported metadata keys. Tracestate requires traceparent. |
| `data` | Payload object limited to 8 levels, 256 keys per level and 64 KiB |

Extensions are scalar. Unknown attributes, `data_base64`, an invalid version, a type without `.vN`, a timestamp without a time zone or a scope other than a JSON object raise `InvalidMessage`. The external profile contains exactly `specversion,id,source,type,time,datacontenttype,data`. Its `data` object comes from the projector. The profile omits internal scope, PII metadata, subject, correlation, causation and traces. It also omits `dataschema` because the redacting projector can remove fields required by the internal schema. Sign the stored bytes without reserializing them.

The `type` is a stable public name independent of PHP class names. Breaking payload changes require a new `name.vN` and a new `dataschema` reference. Schema and envelope versions are independent.

## Mandatory rules

1. A fact's identity includes the account, incarnation, workspace scope, source and message ID. The inbox adds the stable consumer name. It stores the handler major version for diagnostics. The same digest reuses the stored receipt. A different digest at the same key is a conflict.
2. Payloads contain immutable, versioned values. Do not serialize Eloquent models.
3. Declare the message's PII classification and enforce the declared size limit.
4. Publish an integration event only after the change it describes has committed.
5. Declare retries and dead-message handling for each message class. Queue defaults cannot substitute for this policy.
6. Check changes to published schemas against the previous version. `packages/core/tests/Unit/CloudEventCodecTest.php` covers the wire format and round trip.

## Declaring an integration contract

Only a registry declaration makes a fact publishable under `MSG-002`.

1. Use two to four dot-separated name segments matching `[a-z][a-z0-9]*`. The published identity is one string, `name.vN`.
2. Choose `integration-event` or `command`. A `domain-event` cannot be declared by this procedure.
3. Name the owner using the publishing package's Composer name.
4. Declare the maximum PII classification allowed in the payload.
5. Register the entry by overriding the `MessageContractRegistry` binding in the package's service provider.

The `type` defines the compatibility boundary. Compatible changes keep it. A breaking payload change requires a new registry entry at version `N+1`, alongside the existing entry for the announced migration window.

Removing or renaming a field, changing its type, making an optional field mandatory, changing units, meaning, precision or nullability, reusing the field for another purpose or changing an enum so older consumers reject it are breaking changes. A new field is compatible only if the consumer contract tolerates it.

## Delivery guarantees

1. **Ordering.** Claiming outbox rows with `SKIP LOCKED` increases throughput and can process messages out of insertion order. Per-key ordering requires a separate mechanism.
2. **Duplicates.** Delivery is `at-least-once`. Failure between publication and recording consumption can produce duplicates. Consumers must be idempotent and use stable message IDs.
3. **Durability.** `dispatch()->afterCommit()` and the queue setting `after_commit => true` delay publication until commit and discard work on rollback. They cannot satisfy `MSG-008` on their own. After a successful business commit, interruption or failed publication would leave no durable local record to reconcile and retry.

## Producer and consumer transactions

Pass the same Laravel connection object and open transaction used for the business change to `Outbox::append(connection: $connection, message: $envelope)`. The outbox checks admission and stores the fact before commit. A poller picks up ready rows if the post-commit signal is lost. Republishing after a lost acknowledgement preserves the original message ID.

A consumer must acknowledge a duplicate without reapplying business logic or repeating side effects. Acknowledge the broker only after the local transaction commits.

```php
$this->connection->transaction(function () use ($envelope): void {
    if (! $this->inbox->claim(
        connection: $this->connection,
        consumer: new ConsumerId(name: 'search.projection', version: 1),
        message: $envelope,
    )) {
        return; // The original transaction already applied this delivery.
    }

    $this->apply($envelope);
});
```

An external broker adapter passes the `ConsumerId`, an opaque delivery reference and the handler to `BrokerReceiver::consume()`. The receiver reads original bytes through `BrokerDeliverySource`, verifies provenance and supplies the handler with an open transaction. It returns `Applied` or `Duplicate` after the local commit. Unknown contracts enter the broker quarantine journal with the original bytes' SHA-256 and an opaque reference for authorized reconciliation.

A custom consumer entrypoint passes the decoded `DataScope` v3 and `$envelope->workProof()` to `StoredWorkRunner::runStored()` under `RUN-002` and `RUN-003`. The proof binds the scope to the owner and digest of the admitted fact. The runner checks proof and admission in the external control store, restores tenant context and principal and verifies the physical connection before resolving the handler. A direct call from an authenticated context uses `WorkScopeRunner::run()`.

The owner of an external effect reserves the attempt in the control store before calling the network. Lease expiry determines when another worker can claim it. The claim token and epoch fence result writes. Store the provider observation outside the tenant snapshot before writing a local receipt. Following a lost response, leaving `unknown` requires a confirmed provider observation or an authorized operator reconciliation record. A provider's “not found” within its eventual-consistency window leaves the effect `unknown`.

On a closing route, `ProviderRecoveryAuthority` validates the right to write a confirmed local receipt. Recovery runs the owner's registered projector under the original fence. The control store retains the first reference and the complete observation history. Workflow pins the run ID from the first `Accepted` or `Confirmed` observation. A conflicting positive reference quarantines the attempt. A driver may declare separate acceptance and confirmation IDs. Later confirmations remain in the journal. Isolation requires operator authority. Reopening checks unresolved tickets under the route lock before changing the fence.

## Retention

Terminal outbox rows have two retention windows. Published rows use `outbox_retention`, with an installation default of 14 days. Dead rows (`dead`) use `dead_retention`, with a default of 90 days for investigation and auditing.

`inbox_retention` defaults to `null`, so the prune command leaves the inbox intact. Retain an inbox marker for at least as long as its `message_id` can reappear. The `sys_pubsub_messages` default is 30 days. Deleting a pub/sub message can lose an undelivered message. Deleting an inbox marker can allow an already-delivered effect to run a second time. Enabling inbox pruning requires a recorded installation policy as well as a configuration change.

## Webhook projection and external delivery

Installed projectors produce immutable webhook topics from declared message contracts. Arbitrary Laravel events cannot become topics through subscriptions or a second registry. Each projection applies a field allowlist and current PII policy. Personal or restricted data requires an explicit sensitive ability. Secret data is prohibited. A canonical UTF-8 body is limited to 20 KiB, active subscriptions to 100 per account and matching subscriptions to 100 per event. Overflow is recorded as dead with a safe code. Do not truncate or select an arbitrary destination URL. Deployment owns encryption at rest. Cryptographic erasure is outside the delivery contract.

Commit the inbox claim and deliveries in one tenant transaction. The logical scope, subscription, original source/event ID and replay ID form the unique fan-out key. The subscription snapshot is taken at first successful materialization. Network delivery begins after the claim commits. `event_id` identifies the fact and `webhook-id` identifies the delivery. Retries preserve raw body, hash, destination and both IDs. The receiver commits its inbox and effect atomically. Lease expiry revokes the worker's authority to record the result. An in-flight request can still complete at the receiver.

Standard Webhooks HMAC-SHA256 signs `delivery-id.timestamp.raw-body`. A `whsec_` secret must strictly base64-decode to exactly 32 bytes. Each attempt gets a fresh timestamp and signature. The headers are webhook-id, webhook-timestamp and webhook-signature. The receiver checks the signature in constant time, enforces a ±300-second window and deduplicates durably. During overlap, at most two keys produce space-separated signatures. Revocation requires explicit evidence of readiness for the new key. An arbitrary 2xx is insufficient. A compromised key may be revoked immediately with an explicit loss of zero-downtime rotation. Store the SecretRef without the resolved secret. Raw payloads, responses, signatures and secrets must stay out of attempt records, management responses and logs. Live rotation requires host authority.

Production destinations require HTTPS on port 443 and a canonical DNS hostname, without userinfo, query, fragment or IP literals. Every attempt checks the entire A/AAAA set, bounded CNAME resolution and denied ranges, including mixed sets, IPv4-mapped IPv6 and organization-specific ranges. Pin the approved socket address before connecting. Preserve TLS hostname/SNI and peer verification. Redirects, proxies and fallback DNS are forbidden. Timeouts are 3 and 10 seconds. Discard response bodies within the 64 KiB limit and limit headers to 16 KiB. Local TLS tests grant no localhost exception in production.

The lease is 30 seconds. A 2xx confirms delivery and a 410 disables the subscription. Retry with the original effect key only after confirming the effect did not occur. A lost acknowledgement leaves delivery `unknown` until authorized reconciliation. Use full jitter with a 5-second base, a 3600-second cap and a 1-second minimum. Retry-After is limited to 24 hours and the cycle to 12 attempts within 72 hours. The worker restores a verified scope before sending. Remote delivery defaults to disabled.

An operator replay creates a separate delivery with a new effect key, a link to the original delivery and the same fact. Replay requires a confirmed terminal outcome. Replaying a success also requires explicit confirmation and an audit reason. Allow at most three replays in seven days. A range replay must specify the subscription, a UTC window of at most 24 hours, a fixed upper bound, at most 100 deliveries and a scoped continuation. Idempotent repetition returns the original selection and outcome. A disabled or changed endpoint, revoked policy, expired body or removed topic prevents replay.

Bodies live for seven days and delivery history and attempts for 30 days. Shortening the PII body TTL also shortens the replay window. Active deliveries cannot be pruned. A delivery deduplication tombstone lives for the entire period in which the source event could recur. Keep the webhook inbox at least as long as the tombstone. The default indefinite message inbox prevents an old outbox event from triggering another send after body cleanup. Pruning is tenant-scoped, uses the clock and processes at most 100 rows at a time. Neither expiry nor a missing reservation authorizes a refund. [Table ownership and retention](table-ownership.md)
