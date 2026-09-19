# P5 wakeup receipts

**Schema:** p5-wakeup-receipts/1
**Observed:** 2026-09-16

## Requirements

| R | Items | Local case receipts | Installed receipts | Pending effective gates | Owner | Resume |
|---|---|---|---|---|---|---|
| R09 | P5.1, P5.4, P5.6 | ENG-01–05; BIND-01–08 | two neutral installed consumers | release/live engine deployment | P9/P10 | requalify immutable adapter on engine upgrade |
| R10 | P5.2, P5.3, P5.5, P5.6 | WAK-01–08; ROOT-01–06; BRIDGE-01–05 | shared wakeup/workflow installed dist | hosted fleet timing and root operations | P9/P10 | run actual operations cohort |

## Producer edges

| Producer | Contract/version | Consumer | Required receipt | Failure model |
|---|---|---|---|---|
| P5.2 | wakeup/1 | P4 | P5 local wakeup | durable SQL request retries through one runner |
| P5.4 | engine-binding/1 | P7 | workflow binding | missing adapter refuses typed capability |
| P5.5 | wakeup/1 digest consumer | P8 | digest bridge | disabled consumer is absent from handler map |
| P5.6 | installed local dist | P9 | immutable installed receipt | release cohort is separately owned |
| P5.6 | installed local dist | P10 | immutable installed receipt | live timing/IAM remains separately owned |
| P5.6 | installed local dist | P11 | immutable installed receipt | consumer pin/reconciliation remains separately owned |

## Sealed inputs

| Path | SHA256 |
|---|---|
| `findings/P5-engine-qualification.md` | `92ba17d24c6c3e8e0ca26a8b3b483912322372140e2441c27eb83c879f9139a3` |
| `findings/P5-local-wakeup.md` | `3e644aa885e31fc8f7b2a3d01f4d7292ac58d2321610562936e4e0212223242a` |
| `findings/P5-root-routing.md` | `87d845db594bfe116edd32cff22f9b37930f28ac3b4db97e7ba4b4cf9a4d08b1` |
| `findings/P5-workflow-binding.md` | `9ca22fb96c8d4c5d5900d8c9ebaabb06ae195bca81990e4936cc9a277e8e78ed` |
| `findings/P5-digest-bridge.md` | `0bfcd6226801cf31374845f3bfeada9a4f0a18f66f865d5fa56cdad76bc7c696` |
| `research/P5-wakeup-design.md` | `ac703e502544138dd6acebaf3778fe20968186b0aacec14049ed16a4f337f3fc` |
| `research/P5-design-rag.md` | `a6f1c22c688d24b427f2c0f64f2755c154cbba969f7b955dbbc381cf9bc068cc` |
| `decisions/D10-workflow-wakeup.md` | `cf30071864f81d7ce2b882653c8b06dfd5dab36e62d948a8ec1a9d76f1241381` |
| `phases/P5/P5.md` | `c81bbdd13b71204284d446aeb6e31e9f417146a875f01617cf5ec5133d6d9d45` |
| `research/05-integrated-design.md` | `6aca22518b510e17d108e0878baaa512b152596b56d75b185adc73494c117e9e` |
| `research/03-gates.md` | `b9a2ad93007d76152c25dbf2f4c6b908c2182482900c1f7f144c6ffb5857db3f` |
| `findings/P1-requirements-lock.md` | `6c2fd0af8072fd681eb095c748fa67d9fc5aab5dad252cefbf06c6597b36e0c2` |
| `findings/P1-acceptance-map.md` | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` |
| `findings/P2-contract-catalogue.md` | `ee24d2dd236b776ccf14027e7731921d419adb1be2cf39728a2d7ccb0bfb2573` |
| `findings/P2-contract-proposals.md` | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` |
| `phases/P5/P5.1.md` | `2a57793277371ddd15e90361efb50dc9eac5b8db9af5d613a3f3842316340dd0` |
| `phases/P5/P5.2.md` | `a34fa14647884b91cb14343d2df1ed993af97591ff9b6a1ec06842a13ca47b0f` |
| `phases/P5/P5.3.md` | `ae094c4b98549720e9aac0ef9f89e5e2d918395dc3a222133c670973985bb04d` |
| `phases/P5/P5.4.md` | `ca48e8ed61c5648dfdca290bb369602aa01bf2c63eed2790672281db973d244b` |
| `phases/P5/P5.5.md` | `9d74daf9e876424432e63a913ee07938f4f870a38dcbc1dbb3149dc826c74105` |
| `phases/P5/P5.6.md` | `07862a772de3061d26c6a4c529ffdc6caca9569e5c6c0cba1af98ca763c48c6c` |
| `artifacts/qualification/p5/wakeup-install-receipt.md` | `a2e2d79fdfba411d95b3d25f6c33be3e5bfed3ecbb9ac1c5f02c7a9a6e850466` |
