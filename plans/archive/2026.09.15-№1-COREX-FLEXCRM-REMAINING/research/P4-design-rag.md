# P4 design RAG — headless extension practices

**Perplexity:** successful
**Search artifact:** `artifacts/P4-design/perplexity-response.json`
**SHA256:** `f17806380a5e5ebe2baf8a99c28111fbb3c7e444795ff5dc37db42638a089c88`
**Additional focused artifact:** `artifacts/P4-design/perplexity-response.focused.json`
**Additional SHA256:** `ad5b384ab3ae1fcdd7d2a7ec2571a7d98070775fb139b56d47b8efe3a065a553`
Author: plan-designer/gpt-5.6-sol/high; 2026-09-15. D4/D5/D6 retained.

Initial actual successful response was incomplete: it stopped before notification design and
had citations only from Laravel. Owner was immediately notified that notification/digest/saved-view
claims remained unconfirmed. Raw initial response/query preserved with SHA256 `a6f6f1f0bc0f7400dacf263ec1b9513cf06c63121d9175b6821317bcac68fc83`.
Separate concise verification returned independent OWASP/PostgreSQL/AWS sources and the default/
outbox distinctions, but its expected matrix was absent. Focused separate query then explicitly
confirmed safe filter compilation and durable retry/digest practices with limitations. No failed or
incomplete answer alone was accepted. All queries neutral: no internal product names/IDs/paths.

RAG:✅ External claims below checked by direct official-page opening and section search using web
on 2026-09-15. Raw verification carriers artifacts/P4-design/primary-verification.json and
primary-verification.additional.json (SNS dedup/GD direct check), and local
source hashes artifacts/P4-design/source-observations.json. Sources validate general practice only.
RAG:— Exact CoreX ports/placement/SQL/defaults/cases and applicability are local design inferences
in research/P4-extension-design.md; Perplexity supplies no internal API/state/gap/release evidence.

## Primary sources

| Origin | URL | Supported claim |
|:--|:--|:--|
| Laravel | https://laravel.com/framework/docs/12.x/authorization | Policies/gates authorize current user/resource with additional context; descriptors do not mint authority |
| OWASP | https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html | deny by default and validate authorization on every request |
| PostgreSQL | https://www.postgresql.org/docs/18/explicit-locking.html | conflicting row locks serialize writers to the same row until transaction end; no external IO atomicity implied |
| OWASP | https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html | generated filenames, content/type/size validation, authorized upload and storage outside public webroot |
| Laravel | https://laravel.com/framework/docs/12.x/queues#jobs-and-database-transactions | after_commit dispatch waits for commit and rollback discards deferred jobs; cache uniqueness is not durable external delivery |
| AWS | https://docs.aws.amazon.com/prescriptive-guidance/latest/cloud-design-patterns/transactional-outbox.html | same-transaction outbox avoids DB/message dual-write loss; duplicate delivery needs idempotent consumer |
| Laravel | https://laravel.com/framework/docs/12.x/notifications#determining-if-a-queued-notification-should-be-sent | queued shouldSend is a final send-time condition; not universal atomic cancel of in-flight external IO |
| PostgreSQL | https://www.postgresql.org/docs/18/ddl-constraints.html | ordinary UNIQUE treats NULL distinct, explicit alternative semantics required; uniqueness alone proves at-most-one |
| OWASP | https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html | parameterized literal values and allowlisted unbindable identifiers/sort tokens |
| PHP | https://www.php.net/manual/en/ref.image.php | GD has JPEG/PNG decoding and resampling/image output functions; configured resource ceilings/worker termination guarantees remain local proof |
| AWS | https://docs.aws.amazon.com/sns/latest/dg/fifo-message-dedup.html | Concrete FIFO dedup scope and finite five-minute interval illustrate why a stable key alone cannot promise indefinite external dedup |
| Laravel | https://laravel.com/framework/docs/12.x/queries | PDO cannot bind column names; client identifiers/order must be constrained server-side |

## Choice / independent origin mapping

| Choice | Independent origins | What remains local inference |
|:--|:--|:--|
| panel capability/current policy | Laravel + OWASP | reuse existing EntityRegistry/all(ctx), DI read catalogue, explicit guest panel/parent distinction without role engine |
| media attach/delete and upload/conversion | PostgreSQL + OWASP + Laravel | own file row lock, staged immutable objects and durable operations, limits/GD profile, no cross-package FK |
| durable notification retry/cancel | AWS + Laravel + OWASP | own SQL intent/claim epoch, Settings facade, typed unknown/reconciliation and send-admission cancellation boundary |
| restart-safe digest | AWS + PostgreSQL + Laravel | frozen item/channel membership, UTC half-open windows, batches/late catch-up and accepted P5 local_sweep binding |
| saved filter/default scope | OWASP + Laravel + PostgreSQL | bounded typed AST, core-only ports, normalized scope/default markers, nonempty exactly-one library lifecycle |

## Alternatives

- New persisted panel registry/component compiler versus DI descriptors over current mechanisms:
  choose latter; former adds a new extension engine/RFC and ownership drift. Client capability as
  permission rejected because state/provider/access may change between display and execute.
- Synchronous storage delete/refcount-only versus locked authoritative usages + deleting state+
  tracked delayed purge: choose latter. Counter drift or post-check attach creates dangling refs;
  object IO stays outside SQL locks, and unknown result requires reconciliation.
- Adopt full media framework/mandatory vendor SDK versus thin own lifecycle/GD optional driver:
  choose minimal supported candidate. Unqualified extra dependencies/APIs cannot enter P2 silently;
  no-GD upload works, unsupported conversion isn't represented as successful thumbnail proof.
- notify immediately/afterCommit-only versus durable SQL intent with optional afterCommit kick:
  choose durable intent. afterCommit handles visibility, not crash after commit before dispatch.
  Cache unique jobs alone cannot make SMTP/webhook exactly-once; unknown provider effect persists.
- Cron-only volatile digest versus accepted P5 wakeup + durable run/member ledger: choose shared
  wakeup and restart scan, separate pure notification branch. Freeze content/key after admission,
  don't add late items to a possibly already accepted external payload under the same key.
- Replay stored JSON/raw SQL or prune invalid fields versus fail-closed typed filter compile:
  choose allowlists and schema version; binding alone cannot protect identifiers/unauthorized fields.
  PostgreSQL-only NULLS NOT DISTINCT versus portable normalized nonnull marker: choose portable
  marker with CHECK/unique + scope lock, no accidental min-PG upgrade or magic UUID sentinel.

## Cross-phase impact

P2 consumes exact P4 stable proposals, approvals still actual gates. P3 storage branch only feeds
media. P5 shared accepted wakeup only feeds P4.6 scheduled digest; full finish freezes exact P5
path/signature and mismatch repair. P6 built-in/dynamic schema/search translation uses saved-view/1
only when declared; P4 core views don't wait dynamic engine. P7 notification callers require durable
producer intent in same SQL boundary or their own outbox. P8 descriptors are read metadata, tool
execute applies current consumer policy. P9 new packages enter immutable cohort/split/install/matrix/
CI/literal scans. P10 effective provider/storage/timing receipts are distinct. P11 consumer pins and
actual R/AC acceptance cannot be inferred from P4 local/conformance/installed design work.

## Unresolved premises / bounded future obligations

No architectural choice is delegated to execution. P1 actual equivalent inventory/P2 adopted exact
catalogue, API/schema/storage/source approvals, P5 exact internal wakeup binding (P5 detail + whole
finish before any execution), runtime conversions/races/installed probes, full runner/released
cohort and live selected providers are future obligations. No internal product facts from web,
no source dependency version/security/advisory claim, no live delivery/timing acceptance at design.
