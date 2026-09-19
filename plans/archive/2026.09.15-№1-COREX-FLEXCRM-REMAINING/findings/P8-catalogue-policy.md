# P8 catalogue and policy

**Schema:** p8-tool-receipts/1  
**Item:** P8.3

Current-policy tool catalogue projects compiled tool declarations through fresh module
activation and consumer policy on every discover call. No authority cache, routes or
implicit exposure.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| CAT-01 | R08 R36 | Discovery filters installed owner, enabled activation, consumer discover policy and per-tool deny metadata; never full build inventory | `ab5f47960cff6bd6a2de50c4c65308dfafc36012f3ef29748f4b72d7280ed082`; `1e503dfe508fb6e7b709328ad87eb07ec5bbea066b1f26f95435213f1115cdb3`; `b8fa61f65c8da5d6c3f62369ed5506eea7817c31ab55c0972ce4ec9e5c586ed8` | PASS | P8.3 | guarded invocation in P8.4 |
| CAT-02 | R36 | Default policy denies discover and invoke; missing consumer policy does not break ordinary boot | `ab5f47960cff6bd6a2de50c4c65308dfafc36012f3ef29748f4b72d7280ed082`; `c91336bb674fba129279eb2ff9a5d05a7a03b989ea0959b7be785052770be87a` | PASS | P8.3 | consumer adapters replace binding |
| CAT-03 | R36 | Discover allow does not grant invoke; policy surfaces remain separate | `ab5f47960cff6bd6a2de50c4c65308dfafc36012f3ef29748f4b72d7280ed082`; `71383eb747835db9e94aa2f6c0cdccce69eb5e38b0a68103f5d6197d0d012e94` | PASS | P8.3 | invoke guard in P8.4 |
| CAT-04 | R36 | Principal and tenant workspace come from trusted RequestContext, not payload metadata | `ab5f47960cff6bd6a2de50c4c65308dfafc36012f3ef29748f4b72d7280ed082`; `b8fa61f65c8da5d6c3f62369ed5506eea7817c31ab55c0972ce4ec9e5c586ed8` | PASS | P8.3 | transport projection in P8.7 |
| CAT-05 | R36 | Account and principal changes re-read activation and policy without stale catalogue state | `ab5f47960cff6bd6a2de50c4c65308dfafc36012f3ef29748f4b72d7280ed082`; `1e503dfe508fb6e7b709328ad87eb07ec5bbea066b1f26f95435213f1115cdb3` | PASS | P8.3 | worker flush covered in P8.4 |
| CAT-06 | R36 | Activation/epoch changes visible on next discover; no cached authority projection | `ab5f47960cff6bd6a2de50c4c65308dfafc36012f3ef29748f4b72d7280ed082`; `b8fa61f65c8da5d6c3f62369ed5506eea7817c31ab55c0972ce4ec9e5c586ed8` | PASS | P8.3 | cross-process proof in P8.7 |
| CAT-07 | R36 | Deterministic sorted catalogue with explicit 1000-descriptor cap yields catalogue_limit, not silent truncation | `ab5f47960cff6bd6a2de50c4c65308dfafc36012f3ef29748f4b72d7280ed082`; `1e503dfe508fb6e7b709328ad87eb07ec5bbea066b1f26f95435213f1115cdb3` | PASS | P8.3 | transport pagination owner in P8.7 |
| CAT-08 | R36 | Contract hash tracks current compiled bytes; revision is deterministic over projected descriptors | `ab5f47960cff6bd6a2de50c4c65308dfafc36012f3ef29748f4b72d7280ed082`; `e02d0ce4c1e7d4eafa53755fcfae59e1158d405d582589313397de0a7b25a5cd` | PASS | P8.3 | stale invocation rejection in P8.4 |
| CAT-09 | R36 | Entity capability metadata is not a permission grant; catalogue does not leak entity field internals | `ab5f47960cff6bd6a2de50c4c65308dfafc36012f3ef29748f4b72d7280ed082`; `b8fa61f65c8da5d6c3f62369ed5506eea7817c31ab55c0972ce4ec9e5c586ed8` | PASS | P8.3 | introspection redaction in P8.5 |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/p8-catalogue-policy-20260918/evidence/tool-catalogue-policy.log` | `ab5f47960cff6bd6a2de50c4c65308dfafc36012f3ef29748f4b72d7280ed082` | CAT-01–09 pest receipt |
| `packages/api/src/Internal/CompiledToolCatalogue.php` | `1e503dfe508fb6e7b709328ad87eb07ec5bbea066b1f26f95435213f1115cdb3` | catalogue projection |
| `packages/api/src/Contracts/ToolCatalogue.php` | `d021043fde3a01071051c61b1276760b70755546ab78eec39de96e316e27bed3` | discover contract |
| `packages/api/src/Contracts/ToolPolicy.php` | `71383eb747835db9e94aa2f6c0cdccce69eb5e38b0a68103f5d6197d0d012e94` | policy contract |
| `packages/api/src/Data/ToolCatalogueView.php` | `7f7cc960caaad29d7c09b7bb36ee1543bea3c991df1e3030f2ad9e64b9be6b7d` | catalogue DTO |
| `packages/api/src/Internal/DenyToolPolicy.php` | `c91336bb674fba129279eb2ff9a5d05a7a03b989ea0959b7be785052770be87a` | default deny policy |
| `packages/api/src/ToolServiceProvider.php` | `f16a6f08c27b55a38fcc48a3b7475023df2552a2dbd641d4cdeb45e6a8f6cb01` | explicit provider |
| `packages/api/tests/Feature/ToolCatalogueTest.php` | `b8fa61f65c8da5d6c3f62369ed5506eea7817c31ab55c0972ce4ec9e5c586ed8` | CAT feature cases |
| `findings/P8-descriptors.md` | `e02d0ce4c1e7d4eafa53755fcfae59e1158d405d582589313397de0a7b25a5cd` | P8.2 predecessor qualification |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| ToolCatalogue + ToolPolicy + ToolCatalogueView | G-API | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | P8.3 | PASS | invoke/guard in P8.4 |
| current-policy catalogue without authority cache | G-API | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | P8.3 | PASS | transport projection in P8.7 |
