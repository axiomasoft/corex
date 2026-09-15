# P8 design RAG — general public practices, local application separate

**Perplexity:** successful
**Search artifact:** `artifacts/P8-design/perplexity-response.json`
**SHA256:** `3e1324c3ecee2ff99c20f7a98689b42f6d074a2b74e1ec08aecf562ce269de7c`

Complete supplementary schema retrieval: `artifacts/P8-design/perplexity-response.schema.json`, SHA256 `bc552b2c41ebcddeebc378c73d18bc32abd5f41044fb29cac14a7fd7a505fd0b`. Queries exact artifacts/P8-design/perplexity-query.txt and perplexity-query.schema.txt. Initial broad response artifacts/P8-design/perplexity-response.initial.json SHA256 `a74b759e211814574ce6e922d62a58954e7427002bde4428c5c418f7ee532a03` truncated confirmation/idempotency subsection and only2links: immediately notified owner, retained historical partial answer, never sole coverage. Dedicated focused complete response closed that verification gap. No internal names/IDs/R/AC/schema/paths/plane details sent; external sources verify only general standards/practices, **RAG:✅**. Applying them to local CoreX is **RAG:—** from source-observations and P8 candidate, not external assertion about internal state.

## Primary sources

| Origin | URL | Supported claim |
|:--|:--|:--|
| MCP | https://modelcontextprotocol.io/specification/2025-11-25/server/tools | Explicit list/call/schema/annotations/structured results/tool name profile; source-consistent baseline, server access control, no compulsory UX protocol. |
| MCP | https://modelcontextprotocol.io/specification/2026-07-28/server/tools | Published newer profile observed directly: authorization-dependent deterministic tools/list; annotation trust; any-JSON structuredContent and per-request metadata changes. No implicit adoption. |
| JSON Schema | https://json-schema.org/draft/2020-12/json-schema-core | Meta-schema/vocabulary/ref URI identity, unsupported required vocabulary refusal, URI need not be fetched. Offline policy and budgets local choices. |
| JSON Schema | https://json-schema.org/draft/2020-12/json-schema-validation | Validation vocabulary integral numbers/format annotation versus assertion. Local profile not blanket full-draft support. |
| JSON Schema | https://json-schema.org/understanding-json-schema/reference/object | additionalProperties sees same subschema; allOf extension can fail; unevaluatedProperties successful evaluated fields differ. |
| OWASP | https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html | Deny by default, least privilege and permission validation every request; discover does not grant invoke. |
| OWASP | https://cheatsheetseries.owasp.org/cheatsheets/Transaction_Authorization_Cheat_Sheet.html | Server-controlled transaction data and final control gate tied to execution, expiry/single-use authorization; concrete binding/transaction schema app-defined. |
| IETF | https://www.rfc-editor.org/rfc/rfc9110.html#section-9.2.2 | Do not automatically retry non-idempotent requests without known idempotency or knowing original unapplied; HTTP semantics not generic external exactly-once. |
| Opis | https://opis.io/json-schema/2.x/ | Existing PHP schema library candidate documents2020-12 support; exact compatible immutable source/upstream/profile/budget conformance still future qualification. |
| Opis | https://opis.io/json-schema/2.x/php-validator.html | Validator/resolver/format configuration exists; documentation existence not selected engine installed or full conformance proof. |

Direct opens performed2026-09-15 and targeted finds retained artifacts/P8-design/primary-verification.json. RFC first fragment open returned internal tool error; immediately used same official HTML URL successfully and verified non-idempotent clause. No claim based on failed page. Optionallibrary/source qualification is future; no version/licenses/rate/service assumptions imported. Page copies are evidence of reading, not execution. Newer MCP page published and versioning redirect observed; baseline remains2025-11-25 until external owner adopts exact new profile.

## Choice / independent origin mapping

| Choice | Independent origins | What remains local inference |
|:--|:--|:--|
| Single compiled catalogue and explicit handler projection | MCP + OWASP | Api extender/current Registry facts from source; no duplicate registry or auto CRUD. |
| Offline qualified restricted schema profile | JSON Schema + MCP + Opis | tool-schema/1 limits/supported subset/type tree/dependency pin must pass actual source conformance; not all libraries implement budgets. |
| Versioned collision-safe naming and contract binding | MCP + OWASP | one-dot namespace restriction + explicit wire map/hash are local requirements, not guaranteed mapping from source inconsistent regex. |
| Fresh discover/invoke/resource policy and lifetime | OWASP + MCP | module epoch/worker/context behaviour checked locally, actual callback/domain final lock/policy owner conformance. |
| Delegated transactional approval/idempotency/unknown outcomes | OWASP + IETF + MCP | ToolExecutionGuard supplies seam only; consumer owns durable proof/claim/DB/outbox/current-state/provider guarantee; external effect not exactly-once. |
| Installed/fake transport boundary and outcome preservation | MCP + JSON Schema + IETF | Golden2025profile/neutral archive+lab/contribution seal are future local execution, not real public MCP/released/live proof. |

## Alternatives

1. New Manifest agentic/ToolRegistry/AI ledger versus existing Api extender/compiled slice: choose same owner and optional api projection, preserve current8extenders and legitimate Action entry. No second event/auth/schema engine.
2. Expose every operation/generate CRUD from entities versus finite opt-in tool/operation wrappers: choose explicit contribution; metadata availability cannot execute arbitrary classes/SQL/connection selectors.
3. Full handwritten schema validator versus qualified existing engine/restricted source-tested profile: choose latter, deny unresolved refs/budgets/unknownkeyword. Empty object/array and numeric semantics preserved, no coercion or defaults. Format annotation not enforcement.
4. Blind underscore reverse parser versus stricter namespace+sealed bidirectional map: choose no-underscore namespace and all-cataloguecollision check/version suffix. Reject `billing.refund_card` versus `billing_refund.card`, no internal canon rewrite or speculative aggregator namespace.
5. Client confirm/annotation/globalcached allow versus owner atomic guard/current final policy: choose callback guard with read-only default, exact approval binding and guarded mutation denial. Local sameDBresult atomicity separate from externalunknown reconciliation.
6. Adopt latest MCP universally versus frozen external integration profile: choose baseline2025-11-25 from source; newer2026observed differences need ownerprofile/golden/SDKqualifications. Generic ports transport independent.

## Corrections and unresolved premises

Supplementary schema answer incorrectly suggested additionalProperties:false should recognize allOf/$ref union. OfficialJSONSchema page contradicts that: it sees **same subschema**; corrected frozenDESC-08. JSON Schema integer includes integral1.0; lexical JSON number tokens are not automatically non-integers. Schema metadata/defaults don't implement server policy/coercion. No title/serverInfo.name unique across aggregate servers guarantee. Output mismatch cannot prove no side effect or safe retry. Ref/resource cycle limits/offlineonly/argumenthash/approvalproof/transaction locks are local policy, not standards guarantees. Stricter candidate names choose ambiguity rejection, not secretly changing B32source.

Unresolved external premises: exact qualified immutable schema engine/ref/license/selectedkeywords/internaltimeouts and parsing correctness, actual adoptedP2signatures/gate hashes, selected producer contracts, consumer guard/schema/auth implementation and source/transaction/response visibility/providerkeys, realexternalMCPSDK/revision/approval/runtime/PAT/OAuth/PII/rate deployment, actualcohort/hosted/released/DI/AP/perf. Each is explicit execution/source/external prerequisite and owner, no unchosen internal architectural alternative. NO-GO does not complete required includedR.

## Cross-phase impact

P2.3collects frozenP8signatures/profile for actualexactproposal/catalogue; no runtime reverse edge. P3mechanics and control-plane19names remain sourceownershipmap, not tenant endpoints or AP/DIaccepted. P4–P7only actuallyselected contributions retain all source/resource/policy/cursor/decimal/locale/unknownsemantics, optionalbranches independent. P8.5corediagnostic needs no P3live/engine/productmodule. P8.7candidateinstalled3Rseal → P9fullcohort/matrix/hosted/release/compatibilitypassport → P10actualcentral/cloud/fullAPDI/controlplane/perf → P11same immutable consumerreceipts. StableP8candidate added fullfinishhashinputs, D4allP1–P11detail before evenP1execution.49originalparents and19controlplaneexternalnames retained, no upwarddependencies/newframework/sourceorprivateauthority.
