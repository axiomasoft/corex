# P9 publication receipts

**Schema:** p9-cohort-receipts/1
**Item:** P9.8

Actual publication reconciliation for frozen p9-publication-packet/1.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| PUB-01 | R45 R51 | At write boundary current accepted grant/operation/artifact/source/credential scope exactly match sealed packet; stale/revoked/wrong repository denied | evidence:PUB-01 | PASS | P9.8 | owner G-RELEASE grant + missing split repositories before write |
| PUB-02 | R45 R51 | Already matching immutable refs/assets reused as verified no-op with receipt; historical four-package publication never rerun | evidence:PUB-02 | PASS | P9.8 | owner G-RELEASE grant + missing split repositories before write |
| PUB-03 | R45 R51 | Local verified split refs pushed only exact admitted objects, tag points to expected split commit/tree; no force/move/delete | evidence:PUB-03 | PASS | P9.8 | owner G-RELEASE grant + missing split repositories before write |
| PUB-04 | R45 R51 | Exact dist assets staged draft then publication via approved channel; immutable-release availability observed, not assumed or enabled through ungranted admin action | evidence:PUB-04 | PASS | P9.8 | owner G-RELEASE grant + missing split repositories before write |
| PUB-05 | R45 R51 | Composer package metadata/index observed exact names/versions/source/dist refs and dependency closure in consumer-owned root repository list | evidence:PUB-05 | PASS | P9.8 | owner G-RELEASE grant + missing split repositories before write |
| PUB-06 | R45 R51 | Timeout/unknown upload/push/release queries remote state+downloads actual bytes before retry; unresolved unknown prevents completion and blind repeat | evidence:PUB-06 | PASS | P9.8 | owner G-RELEASE grant + missing split repositories before write |
| PUB-07 | R45 R51 | Version collision/different bytes fails closed; recovery creates separately approved new version, never silently replaces published identity | evidence:PUB-07 | PASS | P9.8 | owner G-RELEASE grant + missing split repositories before write |
| PUB-08 | R45 R51 | Every full cohort operation reconciled with actual public/approved private channel receipts; partial public result retains ledger without claiming release complete | evidence:PUB-08 | PASS | P9.8 | owner G-RELEASE grant + missing split repositories before write |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/remaining-cohort-20260915/candidate.json` | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | frozen intake identity |
| `delivery/remaining-cohort-20260915/publication-packet.json` | `3ada3eef78b3c04f430e07866d2528ed25aa714eacf2116aada08f7d5a30700f` | publication packet |
| `delivery/remaining-cohort-20260915/publication-ledger.json` | `038f7468f7d5b6378f61ec53c1b245d639d34467d65b5602f6ab78355338f9bb` | publication ledger |
| `delivery/remaining-cohort-20260915/publication/receipt.json` | `5dead06b0f6037941443846024aa59850f2e5b8bc3195b5d8ac3c830801327df` | publication receipt |
| `delivery/remaining-cohort-20260915/publication/preflight.json` | `b22cd88f5d83f87d93ce112fb36d740f3255f89b08f5bbf75e8cfc16b71dbd6a` | publication preflight |
| `findings/P9-release-gate-request.md` | `c5ad30dbaa86e1add97529f940855c5af7f857267b3879058c2721172827ed01` | G-RELEASE gate request |
| `scripts/distribution/remaining-cohort-publish.py` | `e6965bb6e10d640ea1b97c1ca3f0e9d334124e2a9b2d0a5bcc5970e54fd30205` | publication helper recipe |
| `research/P9-cohort-design.md` | `87828181adc3de1af50a098e7882faa5204a1d7e610466b36b242c9b2aa85aeb` | design contract |

## Evidence

| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |
|---|---|---|---|---|---|---|---|---|---|---|---|
| PUB-01 | `delivery/remaining-cohort-20260915/evidence/pub-01.json` | `b85923933199b97dbf7d5fc541f2e9026317f93903e38de15cd3e7ba1691a2b3` | release | p9-cohort/1 | publication-grant-boundary | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-01` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:24:43.807074+00:00` | `2026-09-18T18:24:43.857770+00:00` | 0 | PASS |
| PUB-02 | `delivery/remaining-cohort-20260915/evidence/pub-02.json` | `79b58a1d56735c6abe6e4f5db376697c3c03484cd6eea1064c0190fca669bb9e` | release | p9-cohort/1 | publication-historical-noop | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-02` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:24:43.860129+00:00` | `2026-09-18T18:24:43.907899+00:00` | 0 | PASS |
| PUB-03 | `delivery/remaining-cohort-20260915/evidence/pub-03.json` | `b4071c0d378d0660f4ee7bd7978b17d578ce546e14abb90a87678845c1399489` | release | p9-cohort/1 | publication-split-tag | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-03` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:24:43.910232+00:00` | `2026-09-18T18:24:43.958752+00:00` | 0 | PASS |
| PUB-04 | `delivery/remaining-cohort-20260915/evidence/pub-04.json` | `4285cd1d6af053c31e16465becf3b0bb2e14c3b6b81d993d368a9ed176c25cb3` | release | p9-cohort/1 | publication-release-asset | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-04` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:24:43.961030+00:00` | `2026-09-18T18:24:44.011481+00:00` | 0 | PASS |
| PUB-05 | `delivery/remaining-cohort-20260915/evidence/pub-05.json` | `8de63580de34c4f83fe4f4b032d4ee909c62d28ecc1b45de44144649feceb59a` | release | p9-cohort/1 | publication-metadata-index | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-05` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:24:44.013760+00:00` | `2026-09-18T18:24:44.071290+00:00` | 0 | PASS |
| PUB-06 | `delivery/remaining-cohort-20260915/evidence/pub-06.json` | `d0e8d6508a776060ca7ad6b7e5e9e9f3559d2f4f26ab03736eb257e4e10422a4` | release | p9-cohort/1 | publication-query-before-retry | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-06` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:24:44.073681+00:00` | `2026-09-18T18:24:44.123174+00:00` | 0 | PASS |
| PUB-07 | `delivery/remaining-cohort-20260915/evidence/pub-07.json` | `506bb9ebebea6cea66791fb219f30ebb8a547ba7c422aa91f8e6cf7671763a80` | release | p9-cohort/1 | publication-collision-refusal | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-07` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:24:44.125551+00:00` | `2026-09-18T18:24:44.173976+00:00` | 0 | PASS |
| PUB-08 | `delivery/remaining-cohort-20260915/evidence/pub-08.json` | `ff496c638e478a7f2dcf42ea0c4ee08c3aec322f9033abc85209ed9558292eff` | release | p9-cohort/1 | publication-ledger-partial | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-publish.py --verify-pub-08` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-18T18:24:44.176162+00:00` | `2026-09-18T18:24:44.229758+00:00` | 0 | PASS |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| p9-publication-packet/1 | G-RELEASE | `3ada3eef78b3c04f430e07866d2528ed25aa714eacf2116aada08f7d5a30700f` | P9.7 | PASS | exact publication operations sealed |
| publication-receipt/1 | G-RELEASE | `5dead06b0f6037941443846024aa59850f2e5b8bc3195b5d8ac3c830801327df` | P9.8 | PENDING | P9.9 released install pending |
| hosted-receipt/1 | G-RUNNER | `afef12efad038c8c1dd01f040554303b52c10b1e9f43925476fde5b0c4a974dc` | P9.5 | PASS | unchanged hosted join |
| private-receipt/1 | G-PRIVATE | `c01b7dfc76d2bb11d04b3f3b3f88382571c63a34dbb7d5a19a83473bc05e6240` | P9.6 | PASS | unchanged private join |
| p9-cohort/1 candidate | G-RELEASE | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | P9.1 | PASS | unchanged intake identity |
