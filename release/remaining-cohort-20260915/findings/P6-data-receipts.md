# P6 sealed data receipts

**Schema:** p6-data-receipts/1
**Item:** P6.8

## Requirements

| R | Items | Local case receipts | Installed receipts | Pending effective gates | Owner | Resume |
|---|---|---|---|---|---|---|
| R11 | P6.1–P6.8 | ENG6-01–05; FIELD-01–08; DDL-01–08 | local source qualification | P9 hosted/release | P9 | qualify immutable public cohort |
| R12 | P6.1–P6.8 | TRN-01–10; EXP-01–08 | local source qualification | P10 live/fleet | P10 | run owned operations profile |
| R32 | P6.6–P6.8 | SRCH-01–08; IDX-01–10 | PostgreSQL A/B and loopback SDK receipt | P11 consumer reconciliation | P11 | accept an immutable consumer pin |

## Producer edges

| Producer | Contract/version | Consumer | Required receipt | Failure model |
|---|---|---|---|---|
| P6.1–P6.7 | field-binding/1, schema-bridge/1, transfer/1, search/1, index-lifecycle/1 | P7 | branch findings | missing capability remains typed unsupported |
| P6.8 | p6-data-receipts/1 | P8 | sealed local receipt | source/local proof is not live authority |
| P6.8 | installed-qualification/1 | P9 | immutable local receipt | release matrix remains separately owned |
| P6.8 | installed-qualification/1 | P10 | immutable local receipt | fleet/IAM timing remains separately owned |
| P6.8 | installed-qualification/1 | P11 | immutable local receipt | consumer acceptance is separately owned |

## Sealed inputs

| Path | SHA256 |
|---|---|
| `findings/P6-engine-qualification.md` | `86984551b3cfb3e0505e034819f07cb66e89357af21baef06f078fcf551220f1` |
| `findings/P6-field-binding.md` | `e2b94ea002a6fe6131676d9b412bb08e08ccd60cb7f335bf52a76aa2715c5c5b` |
| `findings/P6-schema-bridge.md` | `b680c212390540d2f51a73083640cfe43409b80bb38bb5a6c4740a46261ade52` |
| `findings/P6-transfer-runner.md` | `6c53fb489ab97fb6ca268788f6918115bd9ffb261caea6855f94bc0b950a00c4` |
| `findings/P6-export.md` | `29c4bbaa6c7ef64ee5f386cd5222ac666ad32cd47c8402422540566967a5cfbe` |
| `findings/P6-search-query.md` | `6f5f4f0977d62fc70a3696836940bbabc8185453e0bfdab6e6c08636e6b0b8ab` |
| `findings/P6-index-lifecycle.md` | `219011207a031727aea5b32ae9b27a04111fefe1d90a2813fd3fec2befc1d1f7` |
| `research/P6-data-design.md` | `149932325e6a7c48495a81e56e3ae2054f105fe9dde3147a83955f831d3a02cb` |
| `research/P6-design-rag.md` | `9dfeee763636a232dd8ad3773e096398c2b56d1c4fd9216a7ecad3efa298cec2` |
| `decisions/D11-data-import-search.md` | `deb3640ccb7a03be98d24fa515132a6912506dc0af3ff3eb6454c80c66f5b6bc` |
| `phases/P6/P6.md` | `695f529b5c085340542ecffd6eb6de5110cabf267ece91a8e5edfcbf26c3b70c` |
| `research/05-integrated-design.md` | `6aca22518b510e17d108e0878baaa512b152596b56d75b185adc73494c117e9e` |
| `research/03-gates.md` | `b9a2ad93007d76152c25dbf2f4c6b908c2182482900c1f7f144c6ffb5857db3f` |
| `findings/P1-requirements-lock.md` | `6c2fd0af8072fd681eb095c748fa67d9fc5aab5dad252cefbf06c6597b36e0c2` |
| `findings/P1-acceptance-map.md` | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` |
| `findings/P2-contract-catalogue.md` | `ee24d2dd236b776ccf14027e7731921d419adb1be2cf39728a2d7ccb0bfb2573` |
| `findings/P2-contract-proposals.md` | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` |
| `findings/P2-gate-requests.md` | `77b763002a9509b5ab995046ed838bbcc19f9e6c1786cc6da6a6c994172e5cdd` |
| `phases/P6/P6.1.md` | `948e62b68923e8d02adff3d7f7b7c5121d1bdba08aaeb659f83d6da221bbdad2` |
| `phases/P6/P6.2.md` | `9f4bc43e40b4e82892f58398185b66ee203da5defb4595338a9682584a79f63e` |
| `phases/P6/P6.3.md` | `3cb006fe080ed4374fce4a277b12c1556220af87f56b6eb746b6666fa427dd9b` |
| `phases/P6/P6.4.md` | `43625a2a29c0df850cec31dcd23bb4eb327ee7f881c1c857ec1721d1ccd47c0f` |
| `phases/P6/P6.5.md` | `054e14faefd8921fbb84d060d35124b464f5ce5fdc286bff3dced0040e00fd7e` |
| `phases/P6/P6.6.md` | `da86531888352cb73c540b38f07d25bdacc9b00f690cbb278051da739e7a90d0` |
| `phases/P6/P6.7.md` | `45c83f8452b50878388ea9cc1da7281eac54b408d1bfe224788f8ed41f2912b6` |
| `phases/P6/P6.8.md` | `9e0a63f4781d931b043903f58f9d94a2b03e0d98f0987f9d1cead7c781f613da` |
| `artifacts/qualification/p6/data-search-install-receipt.md` | `cca31362f2ce12544e1b1fc46cf689e21ac7f3a07c5d45daaf4e15f0b80b52d2` |
