# P8 tool descriptors

**Schema:** p8-tool-receipts/1  
**Item:** P8.2

Explicit typed tool declarations compile into the existing API slice; bounded offline
`tool-schema/1` validation runs through an isolated Opis adapter supervised subprocess.
No ninth extender, implicit operation exposure, or root eager schema dependency.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| DESC-01 | R08 R36 | Explicit `Api::tool` / `Api::exposeTool` compile into existing API slice with manifest ownership; no ninth extender | `da466e393c668f897f935ef6d5ffdf4e42dd78f129739e7ec364d5d275a3f00d`; `03c63930c1112834d5a41ca53f6ef5a8623dc4fde0ef78273c6e757da1555398`; `029d126f8900df7bf40de3d04b737e61f0b187113ee13f0a44031c7a40f74cec` | PASS | P8.2 | refresh on declaration/compiler change |
| DESC-02 | R36 | Unannotated operations do not become tools; no scanner or implicit exposure | `da466e393c668f897f935ef6d5ffdf4e42dd78f129739e7ec364d5d275a3f00d`; `03c63930c1112834d5a41ca53f6ef5a8623dc4fde0ef78273c6e757da1555398` | PASS | P8.2 | catalogue policy in P8.3 |
| DESC-03 | R36 R50 | Tuple and contract hash stable across declaration order; title not identity | `da466e393c668f897f935ef6d5ffdf4e42dd78f129739e7ec364d5d275a3f00d`; `cfb62a07bef7ede47babe1e2a21e7a345d4f61339ae9c545065883458b54eaed` | PASS | P8.2 | refresh on hash contract change |
| DESC-04 | R36 | Restricted one-dot canonical namespace; wire collision rejected | `da466e393c668f897f935ef6d5ffdf4e42dd78f129739e7ec364d5d275a3f00d`; `03c63930c1112834d5a41ca53f6ef5a8623dc4fde0ef78273c6e757da1555398` | PASS | P8.2 | transport projection in P8.4/P8.7 |
| DESC-05 | R36 | Duplicate tuple, foreign opRef, invalid handler rejected before publication | `da466e393c668f897f935ef6d5ffdf4e42dd78f129739e7ec364d5d275a3f00d`; `03c63930c1112834d5a41ca53f6ef5a8623dc4fde0ef78273c6e757da1555398` | PASS | P8.2 | guarded invocation in P8.4 |
| DESC-06 | R36 | Draft2020-12 local closure offline; remote/dynamic keywords rejected | `da466e393c668f897f935ef6d5ffdf4e42dd78f129739e7ec364d5d275a3f00d`; `73f3bfa9ac0aca29d5afa65ee9d7f3956e551ee0456526419a2e0b7efffa52c7` | PASS | P8.2 | optional suggest only at root |
| DESC-07 | R36 | Object vs array and safe integer profile preserved; duplicate keys rejected | `da466e393c668f897f935ef6d5ffdf4e42dd78f129739e7ec364d5d275a3f00d`; `73f3bfa9ac0aca29d5afa65ee9d7f3956e551ee0456526419a2e0b7efffa52c7` | PASS | P8.2 | owner semantic formats remain domain-owned |
| DESC-08 | R36 | additionalProperties same-subschema; unevaluated extra fields rejected | `da466e393c668f897f935ef6d5ffdf4e42dd78f129739e7ec364d5d275a3f00d`; `73f3bfa9ac0aca29d5afa65ee9d7f3956e551ee0456526419a2e0b7efffa52c7` | PASS | P8.2 | format remains annotation-only |
| DESC-09 | R36 | Resource budget exhaustion rejects before handler; bounded errors | `da466e393c668f897f935ef6d5ffdf4e42dd78f129739e7ec364d5d275a3f00d`; `73f3bfa9ac0aca29d5afa65ee9d7f3956e551ee0456526419a2e0b7efffa52c7` | PASS | P8.2 | supervisor subprocess only |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/p8-descriptors-20260918/evidence/tool-declaration-schema.log` | `da466e393c668f897f935ef6d5ffdf4e42dd78f129739e7ec364d5d275a3f00d` | DESC-01–09 pest receipt |
| `packages/modules/tests/Unit/ToolDeclarationTest.php` | `03c63930c1112834d5a41ca53f6ef5a8623dc4fde0ef78273c6e757da1555398` | declaration/compiler cases |
| `packages/api/tests/Unit/ToolSchemaTest.php` | `73f3bfa9ac0aca29d5afa65ee9d7f3956e551ee0456526419a2e0b7efffa52c7` | schema/profile cases |
| `packages/modules/src/Extend/Api.php` | `029d126f8900df7bf40de3d04b737e61f0b187113ee13f0a44031c7a40f74cec` | explicit tool builder surface |
| `packages/api/src/Data/ToolDescriptor.php` | `cfb62a07bef7ede47babe1e2a21e7a345d4f61339ae9c545065883458b54eaed` | typed descriptor projection |
| `findings/P8-source-qualification.md` | `9bacfc2e8f3a69504afe93e1b9bbd3a7b0c65ff0e98e0763f46b704ce07abe1f` | P8.1 predecessor qualification |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| tool-descriptor/1 + tool-schema/1 + tool-name/1 | G-API | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | P8.2 | PASS | catalogue/policy in P8.3 |
| opis/json-schema 2.4.1 | G-DEPENDENCY | `9c24b9a1058df4b1097295eb5f5b0d4f6ddeef52cc49f5b25803d806e231551e` | P8.2 | PASS | optional suggest + package dev only |
