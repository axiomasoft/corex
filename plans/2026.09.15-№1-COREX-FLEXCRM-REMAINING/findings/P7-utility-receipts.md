# P7 sealed utility receipts

**Schema:** p7-utility-receipts/1
**Item:** P7.9

Eight branch reports are green and the portable utility cohort is qualified through cold isolated two-consumer install evidence. This seal pins the immutable source/adoption graph for downstream P8/P9/P10/P11 without claiming hosted release, vendor live timing, or consumer acceptance.

## Requirements

| R | Items | Local case receipts | Installed receipts | Pending effective gates | Owner | Resume |
|---|---|---|---|---|---|---|
| R17 | P7.1/P7.3; P7.4 optional | ENG7-02; PRIM-01..08 | `artifacts/qualification/p7/installed-qualification-receipt.md` | P9/P11 original B27/B13 parent acceptance | P9/P11 | replay immutable installed cohort |
| R20 | P7.1/P7.2; P7.4 | REC-01..12; MAT-01..09 | `artifacts/qualification/p7/installed-qualification-receipt.md` | P8/P9 calendar/product external slices | P8/P9 | keep recurrence/task materialization local |
| R23 | P7.5/P7.8 | LOC-01..09; OPT-02/03/06/07 | `artifacts/qualification/p7/installed-qualification-receipt.md` | P8 invoke/descriptor receipt | P8 | read descriptors remain non-authoritative |
| R24 | P7.6 | FX-01..10 | `artifacts/qualification/p7/installed-qualification-receipt.md` | P9/P11 finalization/R26 external | P9/P11 | manual rate snapshots only in this seal |
| R25 | P7.7/P7.8 | GEO-01..10; OPT-01/04/05 | `artifacts/qualification/p7/installed-qualification-receipt.md` | Q-P2-052; P10 vendor/live/GEO-08 corpus | P9/P10/P11 | licensed source adoption stays gated |

## Producer edges

| Producer | Contract/version | Consumer | Required receipt | Failure model |
|---|---|---|---|---|
| P7.1–P7.8 | p7-utility-receipts/1 | P7.9 | eight branch findings | missing capability remains typed unsupported |
| P6.6/P6.7 | search/1, index-lifecycle/1 | P7.8 | optional bridge findings | PG path never invokes Meili-only attributes |
| P7.9 | p7-utility-receipts/1 | P8 | sealed local receipt | read label is not invoke authority |
| P7.9 | installed-qualification/1 | P9 | immutable local receipt | release/hosted matrix remains separately owned |
| P7.9 | installed-qualification/1 | P10 | immutable local receipt | fleet/vendor/live timing remains separately owned |
| P7.9 | installed-qualification/1 | P11 | immutable local receipt | consumer acceptance is separately owned |

## Sealed inputs

| Path | SHA256 |
|---|---|
| `findings/P7-source-qualification.md` | `f38399bd43e56d438192f05e9e37f5b8a2600574856a24d2d5088b72e5ebf820` |
| `findings/P7-recurrence.md` | `4654e86c1769528f6e78c49a0172da01dc2dda95aa1bc455bcfe04d7c6c41e8b` |
| `findings/P7-task-primitives.md` | `3329b51f2b552ca6e24ca23b931e60389b7bd78ef0e3c913fe323d3052862773` |
| `findings/P7-task-recurrence.md` | `2dffa1a8f3820cc692ce05dda5c110231f00e8e7ca89cc8ce02f42e6c7936eab` |
| `findings/P7-locale.md` | `9a34389149c33fbcf7827d5b58fba4c986b5ea75183061780adc9264778db2ff` |
| `findings/P7-money-rates.md` | `7ef5b40b594b00fe66c8f0b8a8878363cfabe5ba6722f15b65aa59a933013234` |
| `findings/P7-geography.md` | `74aaabcc3586dca8bafecb10dd49e9199757be33ccef56f5455e1dd7d40a0a23` |
| `findings/P7-optional-bridges.md` | `14597efc2d1fd063ffd2c87732330d3b0eeca92670c477196e4347d7f7ad07bd` |
| `research/P7-utility-design.md` | `e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43` |
| `research/P7-design-rag.md` | `0e9e3b02190e21405acba959670fce23e9921f0e0508d3185c1086100676a7c0` |
| `decisions/D12-portable-utilities.md` | `517b369dbaf33cd5588aa6bdb380286d93b091aece5f1dd87ce05bc50923adb0` |
| `research/05-integrated-design.md` | `6aca22518b510e17d108e0878baaa512b152596b56d75b185adc73494c117e9e` |
| `research/03-gates.md` | `b9a2ad93007d76152c25dbf2f4c6b908c2182482900c1f7f144c6ffb5857db3f` |
| `phases/P7/P7.md` | `2da96803a1cca5b21ceb18958b51a1a53773593cf26a23ce8c699438903613d5` |
| `findings/P1-requirements-lock.md` | `6c2fd0af8072fd681eb095c748fa67d9fc5aab5dad252cefbf06c6597b36e0c2` |
| `findings/P1-acceptance-map.md` | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` |
| `findings/P2-contract-catalogue.md` | `ee24d2dd236b776ccf14027e7731921d419adb1be2cf39728a2d7ccb0bfb2573` |
| `findings/P2-contract-proposals.md` | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` |
| `findings/P2-gate-requests.md` | `77b763002a9509b5ab995046ed838bbcc19f9e6c1786cc6da6a6c994172e5cdd` |
| `phases/P7/P7.1.md` | `22118617a5dfb8a0acaa934b6142780153c880e49ccf06320bcc226830dccb57` |
| `phases/P7/P7.2.md` | `67a0f3fc77df5f7c27a8b55fa134d7588b43d142a68ef8bceb7add0bae1dbc6b` |
| `phases/P7/P7.3.md` | `6a04bc15451bdb2cc7e6cd5b5cd1d3a5a566eb9ee2046b5b5dca18de83762375` |
| `phases/P7/P7.4.md` | `e11e137bb11cea2339890c7d94c76506b90b4412b9f67e3b1732d608fc009b13` |
| `phases/P7/P7.5.md` | `dd2a573adfdda896818ba6f5a2dae194a4407531638d4da8559ebdd8bc0d6103` |
| `phases/P7/P7.6.md` | `b5feae3eba5ee51d3f1f9b80812646413cbf6f8d1a80461a61d88c41cc6313a0` |
| `phases/P7/P7.7.md` | `e488f4abf19961b08e21d7d8b6b403405f97cc60b71519a48c83508a8de3ee8d` |
| `phases/P7/P7.8.md` | `ba52375138e5ba536d647a7cdf43937ef11298b4d4aa97cfa8a4093131169f8d` |
| `phases/P7/P7.9.md` | `a7c9960156f7800e70c28585ec5ea77c781b41e140b5b6c8f3f8c8a2833f04c1` |
| `artifacts/qualification/p7/installed-qualification-receipt.md` | `875a5ac5f7e785635ee3adbfdabc171c9a3c07d77da84ef2ec7350c7cd4eb08b` |
