# P06 design audit remediation — 2026-10-03

Owner instruction: «правь все». Scope: correct A1–A6/m1–m2 in the P06 design, finish, independent fingerprint-bound recheck; read live mirror CI. No P06 product execution, commit, push or CI trigger.

Normative correction: `decisions/D92-p06-design-audit-remediation.md`; D90 history remains. The ATTENTION report and reproductions are preserved in `findings/P06-design-audit.md` and `artifacts/P06-design-audit/`.

| Finding | Corrected contract / scope | Validation evidence |
|:--|:--|:--|
| A1 | 415 definition and occurrence both Validation; core registry unchanged; real non-JSON serialization acceptance added | `http-415-registry.log`: real CompiledProblemRegistry accepts definition and resolves matching category |
| A2 | one Options-backed budgetFailure, decide + SqlWebhookDeliveryStore recovery; exact attempt/time precedence; no store 12/PT72H | P06.4 Files/map/runbook/tests now include lost worker, max=3 and max=20, horizon boundaries/unknown ack |
| A3 | twenty enum reasons include AckUnknown/EndpointChanged; typed HttpResult/retry/store helpers, persisted value boundary, Curl/worker/store/test producers covered, including receiver fixture HTTP401/null and DeliveryTest timeout mapping; DTO public type in exact BC delta | P06.4, examples §7, dossier §1/§3.4/§4; persisted round-trip/finish/rejection acceptance |
| A4 | external profile omits dataschema regardless of internal contract; redacting projector retains safety; internal profile keeps schema | examples §6 external JSON and redaction fixture, P06.3 acceptance |
| A5 | all four qualified item paths expand; diff computed from predecessor base/candidate; baseline-relative read-only state check | `P06.5-read-declarations.json`: no malformed entry; missing only four future completions + future threat doc |
| A6 | package changelogs in every manifest-changing item's Files; manifest/changelog commit coupling, candidate and item-base contract guard | P06.1 operations / P06.2 tools / P06.3 core+webhooks / P06.4 webhooks; exact commands in Validation, base-commit artifact declared |
| m1 | errorCode enum property avoids inherited Exception code collision | `tool-exception-code.log`: PHP 8.4 class loads and catch reads enum |
| m2 | RFC3986 full grammar with pct/scheme/relative/authority/IP-literal syntax; same helper for source and absolute schema URI | `uri-reference-proof.log`: 19 URI + 4 absolute cases pass; primary `http-06-rfc3986.md` retained |

Live mirror evidence: `mirror-ci.json`, queried with gh from academici/corex-mirror. Latest CI run 37123316791 is completed/success on 0c7f7e84819e8730815afdc93e2256c13ab0f0cd: 36 success, 0 failure, 1 skipped Mutation registry (push/PR gate, D76). Mirror HEAD/local HEAD 3f568ddd53520693cd94a7bc3761e9b5ac2412d0 is the plan-only P05 closure with [skip ci]. This is baseline hosted evidence, not CI validation of the new unexecuted design/product.

Plan lint, supporting-artifact validation, sheet/routing and ready-item dry bundle are retained separately after finish. Independent report will bind the new fingerprint; product validation commands in item specs are planned acceptance, not executed outcomes of this remediation.
