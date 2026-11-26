# CoreX naming vocabulary

**Version** 1.0.0 · **Status** normative · **Level** 0 (`corex/*`)

This policy governs class names and public signatures. P3 defines the naming gate. Article `API-009` of the constitution indexes public signatures.

## Choosing a suffix

Use a suffix when it helps a reader predict a class's public methods, dependencies and reason to change. It should also make clear which responsibilities belong elsewhere.

1. Read the name before opening the file. List the operations and dependencies you expect.
2. Inspect the implementation. Unexpected methods, dependencies or side effects suggest that the suffix is too broad.
3. Consider the next method you plan to add. If it fits equally well in several classes, split their responsibilities or rename the classes.

The noun before a suffix must describe the domain just as precisely as the suffix describes the role.

## Suffix vocabulary

| Suffix | Responsibility | Excluded responsibilities |
|:--|:--|:--|
| `Repository` | Access the stored data of one entity | Calculate, decide, dispatch |
| `Factory` | Construct a valid object from inputs | Persist data or read from storage |
| `Resolver` | Select an implementation or value from inputs | Perform the selected operation |
| `Registry` | Hold named contributions and return them deterministically | Create or modify contributions |
| `Policy` | Decide whether an action is allowed and explain why | Change state |
| `Provider` | Participate in the Laravel bootstrap lifecycle | Implement domain logic |
| `Builder` | Assemble a complex value step by step | Execute the assembled operation |
| `Codec` | Convert a value between representations without loss | Validate business rules |
| `Handler` | Perform one named operation | Serve as the entry point for multiple operations |
| `Checker` | Evaluate a condition and return the result as data | Throw an exception in place of a decision |
| `Sweeper` | Find and process overdue work | Schedule future work |
| `Compiler` | Convert declarations into an immutable artifact | Read that artifact at runtime |

## Names requiring justification

New uses of the following names require a documented reason. The P3 gate checks newly introduced names. Historical renames are recorded in `rename-registry.md` (P15.13).

| Name | Rule | Recognized exception |
|:--|:--|:--|
| `Dep` | Removed in P15.1 in favor of `ModuleDependency`. Reintroduction is prohibited. | None |
| `*Info` | Historical use includes `CurrencyInfo` in `axiomasoft/corex-intl` (P16). | An established domain term |
| `Manager` | Avoid new uses. | A narrowly defined technical lifecycle, as in `SearchManager` |
| `Service` | Avoid new uses. | Requires a suffix definition in this policy. None is currently defined. |
| `Data` | STR-006 governs the directory name. | Do not use it as a DTO suffix. |
| `Helper`, `Util`, `Common`, `Misc`, `Processor`, `BaseService`, `AbstractManager`, `DataManager` | Do not introduce them. | The original inventory found no matches. |

## Variables and public signatures

- Name the entity an identifier belongs to, for example `accountId`, `workspaceId` or `actorId`. Use a bare `$id` only inside a method where its type makes the subject clear.
- Name booleans as affirmative statements, such as `$isFrozen`, `$hasWorkspace` and `$allowsRetry`. Avoid negative names such as `$notReady`.
- Use the plural of the contents for a collection, for example `$modules` or `$candidates`. Avoid `$modulesList`, `$modulesArray` and `$modulesCollection`.
- Name both sides of a mapping, for example `$moduleById` or `$definitionsByEntity`.
- Include the unit when the type does not convey it, as in `$maxMemoryMiB`, `$maxRuntimeSeconds` or `$amountMinor`.
- Name an enum case for the value it represents, as in `AttributeLifecycle::Deprecated`.

The P3 gate applies only to public signatures. Review local variable names during code review.
