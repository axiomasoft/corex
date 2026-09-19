# P8 source qualification

**Schema:** p8-tool-receipts/1  
**Item:** P8.1

Read-only qualification of registry/API/schema sources for R08/R36/R50. Existing compiled
API slice and eight-extender pipeline are retained; tool catalogue, schema validator adapter,
registry introspection and MCP transport remain proposed gaps until P8.2–P8.7. `opis/json-schema`
2.4.1 is the immutable schema-engine candidate; it is qualified here but not installed in the
root monorepo vendor tree.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| QUAL-01 | R08 R36 R50 | Inventory distinguishes existing behaviour from proposed gaps with source hashes retained | `781858786a2ba239524a1671ae89d848de7f6d55ea19e69c9326dfbd37bea8f0`; `5c15c38e5be0a3fc6e3771b5e2399195fee51df1c95e76a15b9c4dd639e48823` | PASS | P8.1 | refresh on committed source change |
| QUAL-02 | R08 R36 | Validator source/license/ref/closure and upstream Draft2020-12 suite with supervisor bounds profile | `9c24b9a1058df4b1097295eb5f5b0d4f6ddeef52cc49f5b25803d806e231551e`; `19b827fb4db182637e5a32a7f883bf471d0b73171d92a2f422e7fd97723cd233`; `b24571cdecbffa95b7692d40562ad2c2346544df8977564e72dd69060a0748ee` | PASS | P8.2 | adopt only via optional suggest + G-DEPENDENCY |
| QUAL-03 | R36 | Api extender/compiled slice/OperationHandler path retained; eight extenders unchanged; declaration roundtrip works | `7e3edba6923e8e52eb55953de8c43abf89f5756d24d106bb1277424d41d7b8c7`; `5c15c38e5be0a3fc6e3771b5e2399195fee51df1c95e76a15b9c4dd639e48823` | PASS | P8.2 | extend Api additively only |
| QUAL-04 | R08 R36 R50 | P2 tool/introspection proposals and gate rows match finite bytes; proposal text does not grant adoption | `ab987272a9e372beb43d88b6e7ca594e1f8005bff8c4d6ca5032dec8bef707e6`; `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b`; `ded33e03fc260e632c06a4fec0e66e404cb29e6383e921e24c679ffadd8802ca` | PASS | P8.2/P8.5 | Q-P2-053/054/055 remain proposal |
| QUAL-05 | R08 R36 R50 | Archive/lab discover/invoke/resource policy fixed before code; no private source/HTTP/AI SDK/root calls for baseline | `c80a0758ef4d6b82099b665be47dac0109b1100685198b43009b541e7dcbacbb`; `7aaac37b3815701553a783f5da8d8557fefaabba0e8d87f3f5bcbc3b18262e8a` | PASS | P8.7 | cold install parity in P8.7 |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/tool-source-qualification-20260915/evidence/registry-api-inventory.json` | `781858786a2ba239524a1671ae89d848de7f6d55ea19e69c9326dfbd37bea8f0` | existing vs proposed seam inventory |
| `delivery/tool-source-qualification-20260915/evidence/opis-source.lock` | `9c24b9a1058df4b1097295eb5f5b0d4f6ddeef52cc49f5b25803d806e231551e` | immutable schema engine pin |
| `delivery/tool-source-qualification-20260915/evidence/opis-upstream-suite.md` | `19b827fb4db182637e5a32a7f883bf471d0b73171d92a2f422e7fd97723cd233` | isolated upstream test receipt |
| `delivery/tool-source-qualification-20260915/evidence/schema-profile-keywords.json` | `b24571cdecbffa95b7692d40562ad2c2346544df8977564e72dd69060a0748ee` | tool-schema/1 bounded profile |
| `delivery/tool-source-qualification-20260915/evidence/p2-proposal-bindings.json` | `ab987272a9e372beb43d88b6e7ca594e1f8005bff8c4d6ca5032dec8bef707e6` | C-P2-028/C-P2-029 gate bindings |
| `delivery/tool-source-qualification-20260915/evidence/runtime-profile.json` | `7aaac37b3815701553a783f5da8d8557fefaabba0e8d87f3f5bcbc3b18262e8a` | runtime baseline without optional tools |
| `delivery/tool-source-qualification-20260915/fixtures/archive-lab-policy.json` | `c80a0758ef4d6b82099b665be47dac0109b1100685198b43009b541e7dcbacbb` | discover/invoke/resource policy fixture |
| `delivery/tool-source-qualification-20260915/evidence/api-extender-roundtrip.log` | `7e3edba6923e8e52eb55953de8c43abf89f5756d24d106bb1277424d41d7b8c7` | Api compiled roundtrip proof |
| `artifacts/P8-design/source-observations.json` | `5c15c38e5be0a3fc6e3771b5e2399195fee51df1c95e76a15b9c4dd639e48823` | design-time committed source digests |
| `research/P8-tool-design.md` | `ded33e03fc260e632c06a4fec0e66e404cb29e6383e921e24c679ffadd8802ca` | frozen P8 candidate |
| `findings/P2-contract-proposals.md` | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | sealed P2 proposal bytes |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| tool-descriptor/1 + tool-schema/1 + tool-name/1 | G-API | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | P8.2 | not-applicable | Q-P2-053 proposal; implement after gate |
| opis/json-schema 2.4.1 | G-DEPENDENCY | `9c24b9a1058df4b1097295eb5f5b0d4f6ddeef52cc49f5b25803d806e231551e` | P8.2 | PASS | optional suggest only; no root require |
| registry-introspection/1 | G-API | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | P8.5 | not-applicable | Q-P2-055 proposal |
