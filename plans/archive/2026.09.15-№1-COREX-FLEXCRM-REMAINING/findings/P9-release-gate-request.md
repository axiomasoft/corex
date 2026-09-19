# P9 — G-RELEASE gate request

**Schema:** p9-release-gate-request/1
**Item:** P9.7

Digest-bound publication grant request. Preparer cannot mint consent; P9.8 executes only after owner grant.

## Request

| Request | Gate | R | Exact packet | SHA256 | Owner | Required receipt | Status | Resume | Destination |
|---|---|---|---|---|---|---|---|---|---|
| Q-P9-RELEASE-001 | G-RELEASE | R45 R51 | `delivery/remaining-cohort-20260915/publication-packet.json` | `1fcf165edfe6df4f281b6690321513c79e81138431e1c602194ab199b55796fc` | Release owner | Exact finite cohort operations: split tag + dist release per public package; query-before-retry; no force/delete/admin; private auth excluded from public metadata. | proposal | Owner grants G-RELEASE at current packet digest; stale packet requires re-seal before write. | P9.8 publication |

## Scope digest

- **Packet SHA256:** `1fcf165edfe6df4f281b6690321513c79e81138431e1c602194ab199b55796fc`
- **Branch:** `remaining-cohort-publish`
- **Scope:** `P9.8:p9-publication-packet/1:exact-cohort-operations`
- **Credential refs:** `GITHUB_TOKEN_RELEASE` narrow repo contents/workflow only

## P10/P11 residual handoff (not P9 GREEN by themselves)

| Residual | Obligation | Consumer | Owner |
|---|---|---|---|
| DI01–DI34 | Central/cloud/timing/provider obligations | P10 effective gates | P10 owner |
| AP01–AP13 | Application/product acceptance parents | P11 consumer reconciliation | P11 owner |
| G-API/G-DEPENDENCY | Contract/runtime adoption outside release bytes | P10/P11 qualification | Release owner |
| Live deployment | No live deployed/consumer acceptance in P9 | P10 provider + P11 consumer | Product owner |

## Explicit non-grants

- Historical D14 design authority is not a publication grant.
- Hosted mirror execution (`academici/corex-mirror`) does not substitute canonical split-repo write scope.
- Prepared packet/NO-GO is not phase GREEN; P9.8 owns actual publication bytes.
