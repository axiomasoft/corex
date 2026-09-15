# Roadmap — 2026.09.12-№2-COREX-APPROVED-SPECS

| Phase / Batch | Specs | Dependency/input edge | Dispatch condition |
|:--|:--|:--|:--|
| P1 / B1 | P1.1→P1.2/P1.3/P1.4 | qualified source patches/hashes; shared sources use ordered lineage | separate exact invocation; SOURCE-DOCS for amendments |
| P2 / solo+B2 | P2.1→P2.2→P2.3 | exact DDL approval and consumer template trust/lock bindings→core verifier proof | TEMPLATE-SCHEMA/TEMPLATE-SEAL/public gate |
| P3 / solo | P3.1 Done; P3.2 docs-only | D8 independent projects → doctrine/optional-adapter ownership documentation | separate exact P3.2 invocation; no foreign writer binding |
| P4 / B4 (closed) | P4.1→P4.5 GREEN | committed graph→export→public proof; private proof separately qualified | closed with local/public and isolated private proof; no publication authority |
| P5 / B5+solo | P5.1 GREEN → P5.2 local dossier → P5.3 external operator | public proof→passports→dossier (producer P5.2)→scoped external grant | P5.2 is routine local documentation; P5.3 is a separate finite PUBLICATION owner-gate |

17 detailed specs; current product state is generated from journal. P3.2 and P4 are GREEN; P5.1 is GREEN. Missing external bindings do not reopen 2026.09.12-№1-COREX-SUCCESSOR P3 or block the routine local P5.2 dossier. Non-tenancy export preparation may proceed without adopting unknown stancl channel, but full four-package export/public tenancy GREEN requires provider evidence. Private auth proof remains an auth limitation, not public-release proof. Later eligible bundles are generated only after their real producer output exists; no future placeholder artifact.

## P5 admission repair — 2026-09-15

| Diagnostic | Root cause | Repair and guard |
|:--|:--|:--|
| `AUTHORITY_CONTRACT_GAP` for P5.2 | A `[NEEDS CLARIFICATION]` marker was placed in a routine local-doc item, so the executor rejected it before it could write its own dossier. | P5.2 now explicitly permits the local dossier and records unassigned release values as NO-GO facts; remote authority stays outside its scope. |
| `read-budget-v1` for P5.3 | The P5.2 dossier was named as an ordinary missing file, rather than as P5.3's predecessor output. | P5.3 now declares `producer: P5.2`; its bundle is deferred until P5.2 actually produces the dossier. |
| Invalid handoff `Next` | The raw Task command omitted the required semantic `run-items:` prefix. | Handoff uses the canonical semantic form; the historical P4 launch block is removed. |
| P5.3 could never pass admission | It retained `[NEEDS CLARIFICATION]`, which overrides even a future finite authority grant. | P5.3 has a finite `PUBLICATION` owner-gate; exact remote actions still require a later owner invocation and live preflight. |
| Live P5.3 target preflight | Authenticated GitHub `GET /repos/axiomasoft/{corex-core,corex-modules,corex-tasks,corex-tenancy}` returned HTTP 404 for all four targets on 2026-09-15; the current operator is an active `axiomasoft` admin. | Treat all four mirrors as absent until the exact P5.3 invocation grants their creation. Do not create, push, tag or release from P5.2's local-only authority. |
| P5.3 exact owner grant | The owner confirmed creation of the four mirrors, version/tag `0.1.0-p4.3` / `v0.1.0-p4.3`, and GitHub Releases; Packagist and secrets are excluded. | D16 is the finite publication grant. A fresh `frontier/high` P5.3 invocation performs live reconciliation and may mutate only the four named resources. |

**Next:** run-items: task:plan-run 2026.09.12-№2-COREX-APPROVED-SPECS P5.2

Exact routing and native launch are owned by plan.md and handoff.md; this roadmap does not override either.
