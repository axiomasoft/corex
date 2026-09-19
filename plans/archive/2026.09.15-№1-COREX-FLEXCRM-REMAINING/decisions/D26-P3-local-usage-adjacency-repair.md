---
id: D26
date: 2026-09-15
status: accepted
item: design
items: [design]
supersedes: []
superseded_by: null
---
# D26 — bounded local storage implementation repair

The owner authorized the complete B-P3-LOCAL result and clarified that working behavior and design quality take priority, while requesting faster execution. A reproducing P3.1 test proves that local usageBytes() follows a replaced account-root symlink despite the filesystem handle refusing that root. The boxed implementation has the same defect as ContextTenantStorage.

Add only packages/core/src/Storage/LocalTenantStorage.php to P3.1 Files so both existing local implementations validate the root through the existing ScopedFilesystem guard before measuring usage. This is implementation adjacency inside the already authorized local/boxed storage isolation scope. Preserve all mandatory sources, individual read limits, Validation, unchanged public Contracts, and the exact optional object/SDK receipts D25. No new driver, dependency, schema, production capability or runtime change is admitted.

Also list packages/tenancy/phpunit.xml and add its Pg suite so the newly required per-package physical tests run through the existing composer test/CI entry point. This adds coverage without removing a check or changing a floor. Record failed tests, regenerate the affected item bundle, reseal this finite design correction, and obtain current native pre-mutation admission before the newly listed source write. The original START contract and read receipts remain historical evidence; the live contract governs subsequent writes and closure. This executor repair is not a new protected owner receipt or a whole-plan review replay.

Root Arch and root test both reproduce a single stale architecture allowlist: ObjectTenantStorage, the approved owning adapter, is rejected for using FilesystemManager. Add tests/Arch/LayerTest.php to Files and name only that concrete owning adapter in the existing exact allowlist; all other callers remain forbidden. Preserve both original RED logs and rerun the affected Arch/root suites and anti-weakening gate. This is alignment with the approved adapter, without changing the no-bypass invariant.

## Решение

Сохранить принятое выше scoped решение и его исходную owner provenance без расширения полномочий.

## Почему

Явные обязательные разделы и null metadata нужны текущему native генератору decisions; исходный принятый текст сохранён выше, исходные bytes/digest архивированы отдельно.
