# P9 design RAG — neutral release assurance

**Perplexity:** successful
**Search artifact:** `artifacts/P9-design/perplexity-response.json`
**SHA256:** `a3a12e80d97d2524a280da08ddb846f8bbfd38b059261fbd2cb40ca30d639472`

D4/D5/D6 mandatory retrieval gap closed by short complete neutral query after two incomplete responses were immediately owner-notified. First broad response supports provenance/determinism/partial publication/SemVer; incomplete matrix response supports only delivered minimum/current distinction. Neither is treated as complete proof of omitted topics. Short complete third response covers populated upgrade/refusal/backup limitations and optional private adapter. All queries in raw artifacts generic, no internal names/R/AC/paths/profile transmitted. Actual successful calls retained; no login/tool error relabeled successful. No claim about private project behavior is retrieved from web.

Initial `artifacts/P9-design/perplexity-initial-response.json` SHA256 `4ffb6fddd3436888d6bca377bf94c36990c75f36949c5d9b389eaecba171a959`; matrix incomplete `artifacts/P9-design/perplexity-matrix-incomplete.json` SHA256 `be1d7d990b1048445d6aa9472e9ef4b17031fcbd9941896d4a59839538b822f5`. Web direct retrieval reproducible-builds pages timed out: direct HTTPS retry fetched primary successfully, raw compressed evidence/digests in artifacts/P9-design/primary-source-retrieval.json. No pending generic design premise is hidden by fallback; supplementary retrieval resolves failed fetch. Tool citations/synthesis are not primary proof.

## Primary sources

| Origin | URL | Supported claim |
|:--|:--|:--|
| Composer | https://getcomposer.org/doc/01-basic-usage.md | Application lock install uses exact solution; update resolves a solution, library lock not universally required. |
| Composer | https://getcomposer.org/doc/03-cli.md | Lowest/current options and actual-platform check; flags do not prove every allowed dependency combination. |
| Composer | https://getcomposer.org/doc/05-repositories.md | Repository config belongs consumer root; dependency repository lists are not recursively loaded, source/dist refs differ. |
| Git | https://git-scm.com/docs/git-archive | Named tree archive, tree-ID timestamps differ from commit; export-ignore/subst and modes must be accounted. |
| Reproducible Builds | https://reproducible-builds.org/docs/timestamps/ | Embedded timestamps can prevent identical build bytes; fixed metadata is an explicit recipe choice. |
| SLSA | https://slsa.dev/spec/v1.2/provenance | Provenance separates subjects/build definition/resolved dependencies/run details; manifest alone is no assurance-level certificate. |
| GitHub | https://docs.github.com/en/actions/reference/workflows-and-actions/contexts | Observed run SHA/ref/attempt/job/workflow identities, job.workflow_sha on GitHub.com distinct workflow provenance. |
| GitHub | https://docs.github.com/en/actions/reference/security/secure-use | Least privilege and immutable action references, credentials never treated as portable release evidence. |
| GitHub | https://docs.github.com/en/code-security/concepts/supply-chain-security/immutable-releases | Supported immutable release draft+assets→publication, not cross-repository atomicity or universal enabled policy. |
| SemVer | https://semver.org/ | Public version bytes unchanged once released; local accepted Contract-major policy remains stricter. |
| PostgreSQL | https://www.postgresql.org/docs/16/backup-dump.html | Consistent single-DB logical dump/restoration and failures, roles/globals and cross-DB synchronized snapshots separate. |
| Laravel | https://laravel.com/docs/13.x/migrations | Migration and rollback/pretend commands; SQL preview alone not arbitrary-code preflight or safe recovery. |
| OWASP | https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html | Explicit deny by default and validate permissions on every request, not grant inferred from optional adapter installation. |

## Choice / independent origin mapping

| Choice | Independent origins | What remains local inference |
|:--|:--|:--|
| C1 source/subtree/transform/split/dist/lock linkage | Git + SLSA + Composer | Exact local export transformations and version vector p9-cohort/1. |
| C2 deterministic uploaded ZIP and content versus bytes | Git + Reproducible Builds + SLSA | Fixed mode/timestamp/order/compression recipe and two actual builds required. |
| C3 compatibility cells and actual hosted receipts | Composer + GitHub | Actual declared package constraints/toolchain intersect, selected jobs and unsupported targets. |
| C4 populated upgrade/refusal and recovery scope | PostgreSQL + Laravel | Accepted immutable package baseline/schema/fixtures and safe abort/forward route are local source/test obligations. |
| C5 independent optional private auth/domain policy | Composer + OWASP | Existing A09 graph/real AzGuard proof/private channel source owner, no new role writer. |
| C6 partial publication and immutable collision refusal | GitHub + SemVer + SLSA | Finite operation packet/grant/reconciliation reducer and chosen delivery channel. |

## Alternatives

- Single global version versus exact vector: select compatible actual vector and pin closure; do not republish unchanged upstreams or assume all packages share version history.
- Raw split subtree equality versus mapped release tree: choose explicit Composer/LICENSE/export transformations and file/mode equality; original and transformed tree hashes distinct.
- Auto-generated tag ZIP versus prepared deterministic asset: select uploaded verified asset for byte identity; generated archive gets content verification and independent digest only.
- One happy-path root install versus package/profile minimum/current hosted+released-installed cells: choose explicit matrix, no platform bypass or hidden missing private cell; broad constraints not universal patch proof.
- Fresh migration versus populated upgrade: execute both; preflight negative plus fault/restore/forward reconciliation; migrate:fresh/down/rebaseline would erase baseline and cannot prove upgrade safety.
- Private auth in default graph versus separate explicit consumer opt-in: select public no-credential boot and separate approved private direct/transitive proof; default-deny positive/negative policy ownership stays independent.
- Rerun publisher after timeout versus query-before-retry: select finite durable per-op reconciliation; tag/asset collision needs new actual owner version decision, never replace released bytes.
- Require new signing/PKI/SLSA certification or host admin settings versus existing approved channel: select exact checksum/ref/receipt provenance, use available admitted attestation if supplied but do not claim assurance level or infer global immutability.

## Cross-phase impact

P1/P2 row/equivalent/signature/schema/version/gates→six actual P3–P8 seals→P9 identity/vector/matrix/neutral installed+hosted/private→reviewable publication packet→admitted real delivery→same-cohort P10 effective DI/AP/provider/timing and P11 consumer locks/R outcomes. Engine/provider/SDK qualification and original R/AC scope stay upstream. Ordinary backup cannot establish identity restore witness. CoreX layer scan covers all actual new packages; product/private roots stay external. P9 passport static report does not recreate P8 speculative compatibility generator. Required unsupported product target is retained for owning receipt/delta, not erased for GREEN.

## Unresolved premises / proof classes

Generic practices verified; exact selected versions/engine compatibility/private source/license/channel/grants/hosted jobs/exported archives/baseline migrations/real delivered bytes are future accepted execution inputs, not missing internal design decisions replaced by web. Design does not grant protected adoption. All current source facts are RAG:— artifacts/P9-design/source-observations.json and inspected source/recorded E1–E5 only. No current runner/released/consumer/deployed performance claimed. Parent B11 fixed criteria remain external full scope plus generic receipt slices.
