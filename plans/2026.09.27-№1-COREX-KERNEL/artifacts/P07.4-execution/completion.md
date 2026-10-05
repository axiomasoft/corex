# P07.4 completion

## Candidate

Product commit: `6e7abb425640678e9a00347f7874ebfcc5cf6b29`. Base: `9fe70584928a409b9a6377655ceaa71e4e575cc2`. Product and closure evidence stay in separate commits. The earlier notes for `9e56afe69ef2525f7ec3a68ca3b252e6b9bf1078` are archived as `completion-9e56afe6.md`. Net tracked product delta from that commit to this candidate is `packages/access-azguard/tests/Feature/AzGuardBridgeTest.php`. `WorkspacePermissionLayer` matches `9e56afe6` again: `6e7abb42` restored the coalesce guard that static analysis accepts. CI experiments from `acca84ea` were removed by `f1e0910c`; `.github/workflows/ci.yml` matches `9e56afe6`, and `scripts/ci-native-access.py` is absent.

## Product

Access evaluates native role matrices and `aut_direct_grants`, with entity activity guards before grants and memo. Compilation dispatches `RoleMatrixCompiled` inside the tenant transaction. The unreleased `access-azguard` package owns links, pins, mirroring, Blade directives and the moved implementation classes. Bridge tests cover listener rollback, workspace cache separation, and permission resolution when no workspace is present.

## Validation

`composite-validation-6e7abb42-r6.json` keeps the latest real GREEN result for each of the 29 carriers. Both receipts bind candidate digest `090437a5fb06edfbe8c9206b6c4ee41c6b3fc35787eb70cf63a5fd0cdadc25bd`, and current bytes still match that inventory. `final-owner-r5` supplied 25 GREEN checks. Its `dependencies` child was stopped with SIGTERM (exit -15) when the owner halted the previous session; that is a cancellation, not a recorded product defect. `final-6e7abb42-owner-residual-r6` then ran `dependencies`, `anti-weakening`, `docs` and `mutation --all` serially on the same candidate: all four GREEN. Dependency analysis loaded Redis 6.3.0 and igbinary 3.2.16 from the local extracted ondrej/php modules via `PHP_INI_SCAN_DIR` only for that node. Mutation reported 37/37 sabotage cases detected. `negative-final-6e7abb42-r6/negative-summary.json` shows the five examples §11 carriers passed and restored the watched bytes exactly.

BC on this candidate reuses `final-acca84ea-r2` (541 seconds, exit 0). `final-owner-r5/bc.log` is the relevance proof: published BC inputs are unchanged, the changed bridge test is outside the BC path filter, and the bridge package is absent at the BC base. The verifier reproduced that proof byte-for-byte. No new BC run was started.

review: self-check GREEN

Local self-check only: `self-check-6e7abb42.md`, the invariant table refreshed against this candidate and the receipts above. D109 withdraws further independent review and excludes CI from this acceptance. The completed Grok review verdict stays FINDINGS. This line is not an independent GREEN.

## Scope and delivery

Known CI findings stay in `ci-deferred-findings.md` and were not repaired. Unrelated worktree changes outside this item were left untouched. P07.5 was not executed. The owner asked for that item in a new session, without a reviewer flag.
