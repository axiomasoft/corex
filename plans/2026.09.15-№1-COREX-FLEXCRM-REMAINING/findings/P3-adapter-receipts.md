# P3 adapter receipt seal

Version: p3-adapter-receipts/1. This is a local-proof seal only. It neither
closes P3 nor represents installed, release, consumer or deployed acceptance.

## Requirements

| R | Items | Covered local cases | Installed contract | Pending effective conditions | Owner | Resume |
|---|---|---|---|---|---|---|
| R01 | P3.1 | storage-all-methods; retained-handle-context; traversal-symlink; capability-refusal; native-object-sdk-isolation; physical-worker-reset; archive-lab-storage | no installed consumer contract | object/provider deployment, capability and worker receipts | P9/P10 | qualify installed storage composition then replay native proof |
| R02 | P3.2 | claim-concurrent-key; claim-digest-conflict; claim-uncertain-replay; template-pool-deny; export-before-drop; interrupted-export-drop-restore; archive-lab-maintenance | no installed consumer contract | lifecycle/export worker and archive service acceptance | P6/P9/P10 | bind consumer lifecycle and replay race/archive evidence |
| R03 | P3.3 | identity-n1-n4; identity-no-merge-root-write; jwt-mtls-negative; response-wire-cache-negative; revoke-uncertain-reconcile; transport-worker-reset; archive-lab-identity | no accepted production channel | G-CENTRAL endpoint, issuer/resource and deployed mTLS registration | P9/P10 | obtain G-CENTRAL then execute installed transport receipt |
| R04 | P3.3,P3.6 | jwt-mtls-negative; response-wire-cache-negative; accounts-wire-mtls-negative | no accepted accounts profile extension | exact accounts endpoint/profile extension and current binding | G-CENTRAL/P9 | accept exact external profile then wire consumer transport |
| R05 | P3.4 | counters-overflow-cas; equal-revision-conflict; stale-fence-takeover; concurrent-inbox-retry; invalid-trigger-no-write; independent-witness-restore; populated-migration-refusal | physical mapping remains local explicit construction | installed DB mapping, migration and operational recovery | P9/P10 | qualify tenant-lane installation and replay crash/concurrency |
| R06 | P3.5 | final-insert-update-revoke-regrant; lock-held-lease-expiry; clock-domain-loss-deny; remember-cookie-rollback; all-entry-enforced-or-absent; absolute-session-boundary; archive-lab-final-write | no consumer final writer wired | driver entry inventory, clock/timeout and cookie publication receipts | P9/P10 | bind every real final writer to admission and test live clocks |
| R07 | P3.6 | redis-cold-loss; context-version-key-poison; revoke-regrant-stale-list; fresh-click-deny; accounts-wire-mtls-negative; cache-ttl-unavailable; archive-lab-account-list | no default cache/binding | G-CENTRAL profile, trusted Redis selection and fresh click authorizer | P9/P10 | qualify central list profile and consumer cache/authorization wiring |
| R33 | P3.2 | claim-concurrent-key; claim-digest-conflict; claim-uncertain-replay | no export consumer installed | export/drop operational and recovery service receipts | P6/P10 | execute installed export integrity and rollback scenario |

## Producer edges

| Producer | Contract/version | Consumer | Required receipt | Failure model |
|---|---|---|---|---|
| P3.1 storage receipt | p3-adapter-receipts/1 | P4 | consumer storage composition and capability mapping | absent native provider/capability denies unsupported branch |
| P3.2 claims receipt | p3-adapter-receipts/1 | P6 | installed claim/lifecycle/export receipt | replay or archive ambiguity remains deny/recovery work |
| P3.3/P3.6 transport receipts | corex-membership/1 local candidate | P9 | accepted G-CENTRAL profile plus explicit consumer binding | absent/wrong mTLS, token, echo or profile is unavailable/deny |
| P3.4/P3.5 physical receipts | local physical/final-write candidates | P10 | production mapping, timeout/clock and final entry inventory | stale fence, lease or mapping proof prevents activation |
| all P3 branch receipts | p3-adapter-receipts/1 | P11 | release/deployment receipt after P9/P10 | local PASS cannot substitute release or deployed proof |

## Sealed inputs

| Path | SHA256 |
|---|---|
| findings/P3-storage-context.md | 7ef4c868f1ad94685c9a07fe3eaed5c5251a1377cf0694c586c03d7fd191635d |
| findings/P3-claim-maintenance.md | d5e7bee1c11437de215435b942b47661269a61a6e2c7f2146cd73487822bca3a |
| findings/P3-identity-transport.md | a35089c5b27e4e596779d902037bc498da21bbb30a9f65f4f953bca0dc1ae1ae |
| findings/P3-durable-projection.md | 39b32858064f84ddc3e44b549f9ecd85b3ae59e1b61681666c12f961bcd8aa42 |
| findings/P3-final-write.md | 60b551415462b65ba1f3522df96364140d0deea41be8021558b94ca632cdcfdd |
| findings/P3-account-list.md | f0b05bfd78f1abb899effb7a16929caffd21e33db1c7738f088534999fa77a24 |
| findings/P2-contract-catalogue.md | ee24d2dd236b776ccf14027e7731921d419adb1be2cf39728a2d7ccb0bfb2573 |
| research/P3-adapter-design.md | 05dc04b765f133b4779a173c534e15d0ce6eb7ea020f77b5987cf5e391bf5525 |
| research/03-gates.md | b9a2ad93007d76152c25dbf2f4c6b908c2182482900c1f7f144c6ffb5857db3f |
| research/05-integrated-design.md | 6aca22518b510e17d108e0878baaa512b152596b56d75b185adc73494c117e9e |
| decisions/D8.md | 69c62efb10d483ec735d27ae5b00b029c5bb209d1e1b71cb65baeee957d516d4 |
| phases/P3/P3.md | eda83c240c8ddd1f87c4928105e59a70f27a6f59e46419211c9516e082bdc262 |
| phases/P3/P3.1.md | 053ba425a7facfb4dc52877ca45b54bf92daf952d36df641ee73cbe0cc7bd64b |
| phases/P3/P3.2.md | 9f84b0e02b9449778a3277a8d17531bdf50d7194a64053c11ac89a5100b39f05 |
| phases/P3/P3.3.md | c8c9a7006262fd7e06ae96082fceb28f963e5d3ab3ab190890fa5defe55b9d52 |
| phases/P3/P3.4.md | 6ea35a98ac33f9c061db3718d53cfb126cfadc39084329c5a96adef29c095c92 |
| phases/P3/P3.5.md | b8b6977e6a86e16ecfcbcec6b5b5830dd21b816ffb9dd2f27f0b0296e888f2e6 |
| phases/P3/P3.6.md | de23f093125b8152964cda1b1638e5f476890b0a87daab9e3f17e2840428670e |
| phases/P3/P3.7.md | 5ee8e0b1ac626c5c9a772e89b5a4489c7ab7157b93a546c434757ef54744d609 |
