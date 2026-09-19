# P8 cold installed consumers and transport parity

**Schema:** p8-tool-receipts/1  
**Item:** P8.7

Neutral archive/lab tool surface qualified through immutable ZIP dist installs and in-process
direct versus fake MCP projection parity. Live MCP servers, hosted release matrices and central
control-plane handlers remain external-owner obligations for P9/P10/P11.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| INST-01 | R08 R36 R50 | Cold immutable ZIP install from recorded dist hashes; PHP/Laravel matching profiles; composer lock/dist verify; no path repositories or vendor symlinks | `cec2037aa147354f9891c08387e6228a4d63345e2610ac59ef9103356002b970`; `7788e89d75d540bea8a52364cc41a74924ef14bb5ef65bbd9d33cc7f560e273e` | PASS | P8.7 | P9 hosted matrix |
| INST-02 | R36 | Boxed neutral archive with explicit trusted context/policy boots tools; cross-account/workspace guard semantics hold without tenancy/private/Redis/MCP SDK | `c80a0758ef4d6b82099b665be47dac0109b1100685198b43009b541e7dcbacbb`; `ed03e7b5a7926f0613352464ad29803af5759ee7cd2ad8863d93e4e1e3a78973`; `ba33830b778fd246b9d9bdb1fad111bf234dece4c029494fe739505c068a889f` | PASS | P8.7 | P9 consumer cohort |
| INST-03 | R36 | Direct ToolInvoker and fake MCP projection share descriptor/result/error semantics; golden profile MCP2025-11-25; no live endpoint inferred | `1017c6fc44006df1af107ca954aaa5a55d2e65ecfd9b7b4c985ef5a083836313`; `e87d4bab8b27dd5988c052dcb195d60198a6802a3d0b0a9dceaf14dc2f408e23`; `ed03e7b5a7926f0613352464ad29803af5759ee7cd2ad8863d93e4e1e3a78973` | PASS | P8.7 | transport owner P9/P10 |
| INST-04 | R36 | ToolServiceProvider mounts zero routes/migrations/network; unknown/disabled/forged/unguarded mutation rejects when installed | `ed03e7b5a7926f0613352464ad29803af5759ee7cd2ad8863d93e4e1e3a78973`; `b823a8704dc7139537d617084e70dbe8f149b2cce75f5e9cf7b161722010dcfd` | PASS | P8.7 | consumer guard wiring |
| INST-05 | R08 R36 R50 | Affected api/modules suites, arch/layer/contract guards and branch evidence anchors complete; no hosted/release/deployment invented | `ed03e7b5a7926f0613352464ad29803af5759ee7cd2ad8863d93e4e1e3a78973`; `987b2c8aa944bb3ece6ad893ac6f22a5959234db563b04d1bdd37effae5c3aca`; `843521acbab2f527c5e09d4a48ed800203719b248188192a58d2acb1f468d5b4`; `c09b4c48e3357203ed0dd418cc0c74f79cdb00f3553c143a90942926471ce950` | PASS | P8.7 | P9/P11 matrix |
| INST-06 | R08 R36 R50 | p8-tool-receipts/1 seal binds all branch/raw logs/installed/source/policy/gate hashes with owner/resume for effective external gaps | `cec2037aa147354f9891c08387e6228a4d63345e2610ac59ef9103356002b970`; `ed03e7b5a7926f0613352464ad29803af5759ee7cd2ad8863d93e4e1e3a78973`; `e8a80e771ede2dff9c63d7b7debd4f0d64cd7d875e68110ec2b25a15b294e623`; `d58eb7e3631af26e52e1753b7ec4602ff011734244b2ba60b10826fa66e641e1` | PASS | P8.7 | P9/P10/P11 consume seal |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/tool-install-qualification-20260915/evidence/installed.json` | `cec2037aa147354f9891c08387e6228a4d63345e2610ac59ef9103356002b970` | INST-01 cold install receipt |
| `delivery/tool-install-qualification-20260915/evidence/installed-tool-contract.log` | `ed03e7b5a7926f0613352464ad29803af5759ee7cd2ad8863d93e4e1e3a78973` | INST-02–05 pest receipt |
| `delivery/tool-install-qualification-20260915/fixtures/install-cohort.json` | `7788e89d75d540bea8a52364cc41a74924ef14bb5ef65bbd9d33cc7f560e273e` | archive-lab-tool-surface/1 cohort |
| `delivery/tool-install-qualification-20260915/fixtures/archive-lab-policy.json` | `c80a0758ef4d6b82099b665be47dac0109b1100685198b43009b541e7dcbacbb` | discover/invoke/resource policy |
| `delivery/tool-install-qualification-20260915/fixtures/mcp-golden-profile.json` | `1017c6fc44006df1af107ca954aaa5a55d2e65ecfd9b7b4c985ef5a083836313` | mcp-tools-projection/1 baseline |
| `delivery/tool-install-qualification-20260915/fixtures/probe.php` | `b823a8704dc7139537d617084e70dbe8f149b2cce75f5e9cf7b161722010dcfd` | installed consumer probe |
| `delivery/tool-install-qualification-20260915/verify-installed.py` | `16c123a9a6127b1b19ed5b42d4bd631357dd0a926eb41f63f9ffc402d3d35ebd` | cold install verifier |
| `packages/api/src/Internal/McpToolsProjection.php` | `e87d4bab8b27dd5988c052dcb195d60198a6802a3d0b0a9dceaf14dc2f408e23` | fake MCP adapter |
| `packages/api/tests/Feature/InstalledToolContractTest.php` | `ba33830b778fd246b9d9bdb1fad111bf234dece4c029494fe739505c068a889f` | INST feature cases |
| `delivery/p8-invocation-20260918/evidence/tool-invocation.log` | `987b2c8aa944bb3ece6ad893ac6f22a5959234db563b04d1bdd37effae5c3aca` | P8.4 invocation matrix anchor |
| `delivery/p8-introspection-20260918/evidence/registry-introspection.log` | `843521acbab2f527c5e09d4a48ed800203719b248188192a58d2acb1f468d5b4` | P8.5 introspection anchor |
| `delivery/tool-contribution-qualification-20260915/evidence/tool-contributions.log` | `c09b4c48e3357203ed0dd418cc0c74f79cdb00f3553c143a90942926471ce950` | P8.6 contribution anchor |
| `findings/P8-contributions.md` | `e8a80e771ede2dff9c63d7b7debd4f0d64cd7d875e68110ec2b25a15b294e623` | P8.6 predecessor for seal |
| `delivery/tool-install-qualification-20260915/receipt.md` | `d58eb7e3631af26e52e1753b7ec4602ff011734244b2ba60b10826fa66e641e1` | INST-06 install qualification receipt |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| archive-lab-tool-surface cold install | G-API | `cec2037aa147354f9891c08387e6228a4d63345e2610ac59ef9103356002b970` | P8.7 | PASS | P9 installed consumer |
| mcp-tools-projection/1 fake adapter | G-API | `1017c6fc44006df1af107ca954aaa5a55d2e65ecfd9b7b4c985ef5a083836313` | P8.7 | PASS | external transport owner |
| live MCP/PAT/OAuth/public endpoints | G-API | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | P10 | not-applicable | central deployment owner |
| hosted/release compatibility matrix | G-RELEASE | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | P9 | not-applicable | compatibility passport owner |
