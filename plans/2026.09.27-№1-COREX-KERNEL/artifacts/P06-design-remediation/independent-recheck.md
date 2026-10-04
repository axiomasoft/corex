# Independent bounded recheck — P06 design remediation

verdict: GREEN
review: codex/gpt-6-astra/xhigh
date: 2026-10-03
fingerprint: 121c3dec52ab48041129b808dbe3dc2ebf7fb2d7f56c7cc4775a5ecdbf554167

**All eight original findings are resolved in the corrected design.**
0 unresolved Blocker, 0 unresolved Major, 0 unresolved Minor in this bounded recheck.
No remaining defect introduced by the corrections was demonstrated.

This verdict binds the final `finish-round2.json` fingerprint above, not the earlier
`94ae01bf…` intermediate finish or the original `d9961145…` ATTENTION candidate.
The owner authorized correction of all findings; this is the same independent
reviewer's delta recheck, not a new whole-plan or phase audit. Historical reports
and reproductions were not edited.

## Finding disposition

| Original finding | Result | Corrected contract and evidence |
|:--|:--|:--|
| **A1 — 415 registry mismatch** | Resolved | D92 §1, examples §2 line 68 and dossier §3.1 now use 415/Validation consistently. The actual `CompiledProblemRegistry` accepts the definition and the actual `ApiError` produces Validation. P06.1 Validation explicitly requires the real registry and non-JSON serialization case. Core category behavior is unchanged. |
| **A2 — lost-lease budget** | Resolved | D92 §5, dossier §3.4 and examples §7 define a single Options-backed `budgetFailure(delivery, instant)` used by both normal retry and `SqlWebhookDeliveryStore::recoverExpired`. The store is now in Files and Required Reads. Tests name max=3/attempt=3, max=20/attempt=12, ages 3599/3600/7200, equal horizon and overlapping limits. Unknown acknowledgment and lease fencing are preserved. This directly covers both original counterexamples. |
| **A3 — persisted reasons and typed consumers** | Resolved | D92 §6 adds `EndpointChanged` and `AckUnknown`; P06.4 now includes the durable store and Curl transport, typed DTO/helpers, `?->value` at persistence boundaries, the DTO public delta and package changelog. The final same-continuation amendment also includes `tests/Fixtures/LocalWebhookReceiver.php`, maps its bad-signature response to HTTP 401/null, retains tampered-body/effects assertions, and maps the delivery test's `network_timeout` to `NetworkFailure`. All existing constructor locations found by the focused source search are covered. |
| **A4 — external dataschema after redaction** | Resolved | D92 §3 supersedes the external attribute list: `encodeExternal` always omits `dataschema`; internal `encode` retains it. Examples §6 external JSON and the required `{id,secret}` → `{id}` redaction scenario agree. P06.3 separately requires no emitted secret and no false internal-schema claim. CloudEvents makes dataschema optional, so the correction preserves the adopted format without introducing another SPI. |
| **A5 — P06.5 reads** | Resolved | Required Read 2 now expands the fully qualified `P06.{1,2,3,4}.md` paths. The source-free diff entry is removed; Code Guidance computes the diff from exact predecessor base/candidate SHAs. `P06.5-read-declarations.json` has no malformed entries; its five missing paths are explicitly predecessor-produced completion/threat-model artifacts. D92 is included in Inputs and ordered review guidance. |
| **A6 — missing changelog scope** | Resolved | Each manifest-changing item's Files now includes its package changelog: operations; tools; core plus webhooks; webhooks. Dossier §4 and each Validation require manifest/changelog in the same product commit, a saved pre-item base, the candidate SHA and both candidate/item-base contract-guard checks. This matches the actual committed-diff behavior of `scripts/contract-guard.sh`. |
| **m1 — Exception::$code collision** | Resolved | D92 §2, P06.2 Implementation Rules and dossier §3.2 use `public readonly ToolsErrorCode $errorCode`; catches read `$e->errorCode`. The isolated PHP 8.4 class loads, throws and catches the enum correctly. |
| **m2 — URI character filter** | Resolved | D92 §4 replaces the filter with the RFC 3986 grammar in examples §6c, shared by source and dataSchema validation. Percent triplets, relative first segments, authority and IPv6/IPvFuture syntax are checked. Existing source nonempty/128-byte limits remain; the adopted absolute-schema rule requires a scheme and excludes fragments. The executed grammar is byte-identical to the normative skeleton. |

## Evidence checked

Read D92; corrected P06.1–P06.5; the affected dossier/map/runbook/BC sections;
examples §§2, 5–7; remediation.md; the three proof scripts/logs; the corrected P06.5
read declarations; and both finish records. Compared the corrections with the actual
registry, ApiError, retry/store/worker/transport, webhook DTO and fixture consumers,
and contract-guard implementation. Prior unchanged review evidence was retained.

Independently reran these small no-database reproductions with `/usr/bin/php8.4`:

- `http-415-registry.php`: exit 0, registry accepted, definition and occurrence both
  `validation`.
- `tool-exception-code.php`: exit 0, `ToolSchemaFailure loaded/caught: tools.schema_limit`.
- `uri-reference-proof.php`: exit 0, 19 URI cases plus 4 absolute-URI cases GREEN.
  Compared its class bytes with examples §6c and inspected the grammar against the
  already captured RFC 3986 Appendix A. No fresh external premise was needed.

A focused `rg` search for every existing `new WebhookHttpResult` identified the
receiver fixture seam during the recheck. `independent-fixture-seam.php` reproduces
its original string/enum TypeError by combining the real receiver fixture with an
isolated representation of D92's new DTO signature; it opens no socket or database
and changes no product. The final correction explicitly migrates that fixture and
its `WebhookLoadContourIntegrationTest` assertion. HTTP 401 with null transport
error preserves signature rejection and is classified by HTTP status. This proof
records why the final amendment was needed; it is not a claim that the future
product implementation has already been executed.

`WebhookLoadContourIntegrationTest` is a PostgreSQL scenario: the declared package
test command excludes the pg group, while the also-required `composer test:pg`
provides its execution lane. No test execution from either lane is claimed here.

## Scope and limits

The reviewed outcome is a feasible, internally consistent correction of the eight
reported design defects. P00–P05/P10 and unrelated P06 choices were not reopened.
Future runtime acceptance remains the owning items' responsibility, including
budget recovery/persistence, redacting CloudEvents output, tampered-body rejection,
the complete URI cases, PostgreSQL tests, public manifests/changelogs and final CI.

The reviewer wrote only this report and `independent-fixture-seam.php` in the approved
remediation directory. No normative design/product/test carrier, original ATTENTION
artifact, journal/state/handoff, commit or remote was changed; no full suite or
database test ran. Root-owned final carrier/fingerprint checks remain with the owning
root. This GREEN permits its normal design-gate finalization; it does not itself
implement P06 or certify P06 runtime/hosted CI.
