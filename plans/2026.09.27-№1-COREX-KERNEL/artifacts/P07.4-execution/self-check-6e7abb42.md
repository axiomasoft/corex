# P07.4 self-check

Candidate: `6e7abb425640678e9a00347f7874ebfcc5cf6b29`. Base: `9fe70584928a409b9a6377655ceaa71e4e575cc2`. This is the executor's local invariant check against the current receipts. It is not an independent review. The prior write-up for `9e56afe6` is `self-check-9e56afe6.md`.

| Invariant | Inspection and behavioral evidence |
|:--|:--|
| Unknown/inactive entities deny before every grant and memo | `final-owner-r5` access tests and access-pg are GREEN, including NativePermissionTest paths. |
| Native access uses its own matrix and direct grants | Same receipts: access closure scan is empty outside CHANGELOG, and PostgreSQL access tests are GREEN. |
| Compiler and bridge writes share the tenant transaction | `bridge-tests` on `final-owner-r5` is GREEN, including the added null-workspace and workspace-cache cases. Net source versus `9e56afe6` does not change `WorkspacePermissionLayer`. |
| Invalidation waits for commit and preserves original tenant | Covered by the same GREEN bridge tests. |
| Bridge owns links and accesses public seams | Graph, manifests, ownership, tables and naming are GREEN on `final-owner-r5`. Examples §11 negatives on this HEAD passed and restored exact bytes. |
| Configuration remains typed and owned by bridge | `analyse` is GREEN on `final-owner-r5` after the coalesce guard. `dependencies` on `final-6e7abb42-owner-residual-r6` is GREEN with the native Redis/igbinary runtime. |
| Public delta remains finite | BC relevance proof matches the original `final-acca84ea-r2` GREEN log. Golden delta evidence is `golden-semantic-delta.json`. API writes were byte-identical on `final-owner-r5`. |
| Mutation doctrine | `mutation --all` on the residual receipt is GREEN: 37/37 sabotage cases detected. |

No current P07.4 product defect is recorded. CI findings and the tasks-workflow → wakeup exception stay outside this acceptance: CI by D109, wakeup by P07.5.
