# P9 private auth receipts

**Schema:** p9-cohort-receipts/1
**Item:** P9.6

Independent optional private auth install for frozen private-read-packet/1.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| PRIVATE-01 | R45 R51 | Exact accepted G-PRIVATE source/channel/ref/license/checksum and narrow read scope verified before private fetch; no historical blanket D14 authority | evidence:PRIVATE-01 | PASS | P9.6 | P9.7 publication packet |
| PRIVATE-02 | R45 R51 | Cold direct corex/auth install plus transitive example consumer install from approved private channel, separate homes/caches, lock+vendor ref verified | evidence:PRIVATE-02 | PASS | P9.6 | P9.7 publication packet |
| PRIVATE-03 | R45 R51 | Real installed auth suite/default-deny missing grant/unknown resource and current tenant/department scope positive and negative evidence; no role engine duplicate | evidence:PRIVATE-03 | PASS | P9.6 | P9.7 publication packet |
| PRIVATE-04 | R45 R51 | Explicit adapter-missing host public boot denies protected abilities safely; public no-credential graph independently remains usable | evidence:PRIVATE-04 | PASS | P9.6 | P9.7 publication packet |
| PRIVATE-05 | R45 R51 | Laravel12 approved PHP intersection tested; Laravel13 refused unless independent engine owner source/compatibility+G-API/G-DEPENDENCY approval actually supplied | evidence:PRIVATE-05 | PASS | P9.6 | P9.7 publication packet |
| PRIVATE-06 | R45 R51 | Private source/token material isolated and redacted; receipts only hashes/secret refs, no upload of private dist/source or private metadata to public registry | evidence:PRIVATE-06 | PASS | P9.6 | P9.7 publication packet |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/remaining-cohort-20260915/candidate.json` | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | frozen intake identity |
| `delivery/remaining-cohort-20260915/private-read-packet.json` | `058cf1ee78e7f7878ac3a0a63011acf3bd87ad2183269d00e9302c6c686344f0` | private read packet |
| `delivery/remaining-cohort-20260915/hosted/receipt.json` | `afef12efad038c8c1dd01f040554303b52c10b1e9f43925476fde5b0c4a974dc` | hosted receipt |
| `delivery/remaining-cohort-20260915/installed-candidate/receipt.json` | `ebf9fb5aefbbf98bdc1da7d1c804d59df860c67ca1b006d0a210564514ef6042` | installed candidate receipt |
| `delivery/remaining-cohort-20260915/private/receipt.json` | `c01b7dfc76d2bb11d04b3f3b3f88382571c63a34dbb7d5a19a83473bc05e6240` | private receipt |
| `scripts/distribution/remaining-cohort-private.py` | `eab731d6480b81aff67019e827f553318144dd2f989faf01b3783dfc231edca1` | private proof recipe |
| `research/P9-cohort-design.md` | `87828181adc3de1af50a098e7882faa5204a1d7e610466b36b242c9b2aa85aeb` | design contract |

## Evidence

| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |
|---|---|---|---|---|---|---|---|---|---|---|---|
| PRIVATE-01 | `delivery/remaining-cohort-20260915/evidence/private-01.json` | `a8b8799987899f9e6f9d1267d41041ccb6b1d67860956418b46ca65ec8c37289` | private | p9-cohort/1 | private-preflight | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-private.py --verify-private-01` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:40:04.649517+00:00` | `2026-09-19T05:40:04.684035+00:00` | 0 | PASS |
| PRIVATE-02 | `delivery/remaining-cohort-20260915/evidence/private-02.json` | `39dd360b8b6762d48a7660885da956da84bcaffbec126b09ea0c20671331e8c5` | private | p9-cohort/1 | private-install | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-private.py --verify-private-02` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:40:04.686243+00:00` | `2026-09-19T05:40:04.715864+00:00` | 0 | PASS |
| PRIVATE-03 | `delivery/remaining-cohort-20260915/evidence/private-03.json` | `96407b77e00645b9ca63d8bb0f804895856134815d0d0439a0ae9be6a0f56907` | private | p9-cohort/1 | private-suite | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-private.py --verify-private-03` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:40:04.717947+00:00` | `2026-09-19T05:40:04.750633+00:00` | 0 | PASS |
| PRIVATE-04 | `delivery/remaining-cohort-20260915/evidence/private-04.json` | `c27bdda0e21e923ce5c2a2c782d31185dd6de15e614cae3595d9f7cef9e2ae78` | private | p9-cohort/1 | private-public-boot | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-private.py --verify-private-04` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:40:04.752882+00:00` | `2026-09-19T05:40:04.787116+00:00` | 0 | PASS |
| PRIVATE-05 | `delivery/remaining-cohort-20260915/evidence/private-05.json` | `5fea1d9cf3126f5ec89e15fa28fa8c4acc7721c9c3bd27614d1ade66c9beef08` | private | p9-cohort/1 | private-laravel-matrix | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-private.py --verify-private-05` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:40:04.789052+00:00` | `2026-09-19T05:40:04.821211+00:00` | 0 | PASS |
| PRIVATE-06 | `delivery/remaining-cohort-20260915/evidence/private-06.json` | `9fa3ffb4cbd4883b3b3eb478f9b95d24c35acbc05b75e0ae02f5adad86f41fc1` | private | p9-cohort/1 | private-redaction | `/usr/bin/python3 /home/vostrikov/projects/packages/corex/scripts/distribution/remaining-cohort-private.py --verify-private-06` | `/home/vostrikov/projects/packages/corex` | `python=3.12` | `2026-09-19T05:40:04.823196+00:00` | `2026-09-19T05:40:04.853322+00:00` | 0 | PASS |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| private-read-packet/1 | G-PRIVATE | `058cf1ee78e7f7878ac3a0a63011acf3bd87ad2183269d00e9302c6c686344f0` | P9.4 | PASS | exact private read executed |
| private-receipt/1 | G-PRIVATE | `c01b7dfc76d2bb11d04b3f3b3f88382571c63a34dbb7d5a19a83473bc05e6240` | P9.6 | PASS | P9.7 publication packet |
| hosted-receipt/1 | G-RUNNER | `afef12efad038c8c1dd01f040554303b52c10b1e9f43925476fde5b0c4a974dc` | P9.5 | PASS | unchanged hosted join |
| p9-cohort/1 candidate | G-RELEASE | `056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28` | P9.1 | PASS | unchanged intake identity |
