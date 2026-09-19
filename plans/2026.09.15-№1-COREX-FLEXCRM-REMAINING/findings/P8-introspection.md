# P8 registry introspection

**Schema:** p8-tool-receipts/1  
**Item:** P8.5

Bounded registry introspection over compiled module bytes: eight-extender ownership map,
entity/field/capability projection and read-only `why()` diagnostics with redacted output.
Local operator CLI `corex:modules:why` emits deterministic text/JSON; tenant verdict requires
explicit trusted `--account` context.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| INTRO-01 | R50 | extensionPoints returns versioned eight-extender/entities/fields/capability ownership from compiled bytes | `843521acbab2f527c5e09d4a48ed800203719b248188192a58d2acb1f468d5b4`; `ee6de9ddc5db762b605432dd21d9fd4760eab403c8b529f07eac6f8084a60903` | PASS | P8.5 | contributions in P8.6 |
| INTRO-02 | R50 | why explains installed/dependency/active versus activation decision; unknown module explicit | `843521acbab2f527c5e09d4a48ed800203719b248188192a58d2acb1f468d5b4`; `04831de3b965357004f923db2679d922af75e885246367884e265b48508d00d9` | PASS | P8.5 | consumer activation adapters |
| INTRO-03 | R50 | corex:modules:why CLI deterministic; code-only lacks tenant verdict | `843521acbab2f527c5e09d4a48ed800203719b248188192a58d2acb1f468d5b4`; `6c7208a54f7c3427c199c591567016dc231036b537fec4fd8642044924568b3f` | PASS | P8.5 | transport projection in P8.7 |
| INTRO-04 | R50 | diagnostics omit secrets, class refs, paths and connection topology | `843521acbab2f527c5e09d4a48ed800203719b248188192a58d2acb1f468d5b4`; `ee6de9ddc5db762b605432dd21d9fd4760eab403c8b529f07eac6f8084a60903` | PASS | P8.5 | consumer discover auth |
| INTRO-05 | R50 | compatibility view reports declared constraints only; passport pending P9 | `843521acbab2f527c5e09d4a48ed800203719b248188192a58d2acb1f468d5b4`; `6495f961be287a80963d47ba10ebc6b1c79bf979dc1f204ed79e688cfe4c1898` | PASS | P9 | cohort passport |
| INTRO-06 | R50 | no module state writes, filesystem scans, reflection or network from introspection | `843521acbab2f527c5e09d4a48ed800203719b248188192a58d2acb1f468d5b4`; `04831de3b965357004f923db2679d922af75e885246367884e265b48508d00d9` | PASS | P8.5 | read-only contract only |
| INTRO-07 | R50 | missing/corrupt input, unknown extender and stale revision are distinct failures | `843521acbab2f527c5e09d4a48ed800203719b248188192a58d2acb1f468d5b4`; `04831de3b965357004f923db2679d922af75e885246367884e265b48508d00d9` | PASS | P8.5 | consumer revision cache |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/p8-introspection-20260918/evidence/registry-introspection.log` | `843521acbab2f527c5e09d4a48ed800203719b248188192a58d2acb1f468d5b4` | INTRO-01–07 pest receipt |
| `packages/modules/src/Contracts/RegistryIntrospection.php` | `b056053afb9c96fcc4351d2f6783d7b43186f9cc03acd3c3cd80bc37836dfbb3` | introspection contract |
| `packages/modules/src/Data/RegistryMap.php` | `4e55c67f88b5dd27f2f484c448ab09ce5f7e79eb66cae5eed1c3f5173d820d0d` | map DTO |
| `packages/modules/src/Data/ModuleExplanation.php` | `6495f961be287a80963d47ba10ebc6b1c79bf979dc1f204ed79e688cfe4c1898` | explanation DTO |
| `packages/modules/src/Internal/ReadRegistryIntrospection.php` | `ee6de9ddc5db762b605432dd21d9fd4760eab403c8b529f07eac6f8084a60903` | read-only implementation |
| `packages/modules/src/Commands/WhyCommand.php` | `6c7208a54f7c3427c199c591567016dc231036b537fec4fd8642044924568b3f` | operator CLI |
| `packages/modules/src/ModulesServiceProvider.php` | `02900875dc9356492ce0f1d732fabf7993fde5a430be0ef09d028f77a5dab77f` | provider bindings |
| `packages/modules/tests/Feature/RegistryIntrospectionTest.php` | `04831de3b965357004f923db2679d922af75e885246367884e265b48508d00d9` | INTRO feature cases |
| `findings/P8-source-qualification.md` | `9bacfc2e8f3a69504afe93e1b9bbd3a7b0c65ff0e98e0763f46b704ce07abe1f` | P8.1 predecessor qualification |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| RegistryIntrospection + RegistryMap/ModuleExplanation | G-API | `b056053afb9c96fcc4351d2f6783d7b43186f9cc03acd3c3cd80bc37836dfbb3` | P8.5 | PASS | contributions in P8.6 |
| bounded registry why CLI | G-API | `6c7208a54f7c3427c199c591567016dc231036b537fec4fd8642044924568b3f` | P8.5 | PASS | installed seal in P8.7 |
| compatibility passport | G-API | `6495f961be287a80963d47ba10ebc6b1c79bf979dc1f204ed79e688cfe4c1898` | P9 | not-applicable | cohort passport |
