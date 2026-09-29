# P04 — worked examples and file skeletons

Normative: D63/D64; research/P04-design.md owns algorithm/rollout. New architecture code
below is guidance for frontier outcomes, not claimed implemented runtime. No product files
were created by design. The implementation/high item P04.4 uses fixed CLI/rollout §4.

## 1. Api/Spi bootstrap

`packages/core/src/Attributes/Api.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Attributes;

use Attribute;

#[Api(since: '1.0')]
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Api
{
    public function __construct(public string $since = '1.0') {}
}
```

`SpiKind.php` is `#[Api(since: '1.0')] enum SpiKind: string` with cases
`Implement = 'implement'`, `Call = 'call'`. `Spi.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Attributes;

use Attribute;

#[Api(since: '1.0')]
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Spi
{
    public function __construct(
        public SpiKind $kind,
        public string $since = '1.0',
    ) {}
}
```

Bootstrap self annotation works without instantiating classes: AST resolves `Api` to
`CoreX\Attributes\Api`; NameResolver reports since/key literal. Fourth arbitrary file in
Attributes root receives module-layout R3; only these exact 3 files exempted by D63.
Runtime test `AttributesTest`: instantiate Api/Spi, confirm default since and backed enum;
Reflection on method attribute target rejects via newInstance; alias `PublicApi` on a
class resolves to real Api, interface/trait/enum declarations accepted by scanner.
Classes carrying both Api and Spi fail independent of attribute order.

## 2. Scanner input → diagnostic / manifest

These examples become isolated source-tree fixtures in `packages/core/tests/Attributes/`;
fixtures do not mutate live production declarations. All negative diagnostics validated
with path/line/symbol, not just a nonzero process exit.

| Input fixture | Expected strict diagnostic |
|:--|:--|
| unmarked `Demo\Data\Loose` outside Internal | `API-UNCLASSIFIED Demo\Data\Loose` |
| `#[Api] final readonly class View { public Model $row; }` | `API-PAYLOAD … Model` |
| `@return list<array{child: Foreign\Internal\Row}>` on Api method | `API-INTERNAL-TYPE` with full nested path |
| `@return Collection<int, TaskModel>` / `?array` without item type on view | `API-PAYLOAD` / `API-UNTYPED-PAYLOAD` |
| alias + group-use + fully-qualified reference to unmarked foreign type | `API-IMPORT` for all three spellings |
| consumer implements Api interface | `API-IMPLEMENT` |
| `#[Spi(kind: SpiKind::Implement)] interface Driver` | clean; snapshot layer=`spi-implement`, required methods + protected hooks captured |
| Internal class with Api/Spi | `API-INTERNAL-ANNOTATION` |
| malformed PHP / unknown PHPDoc alias | exit 2 parse config error / `API-UNRESOLVED-TYPE` |
| changing only method body/comment | generated manifest bytes unchanged |
| renamed parameter/default/enum backing value | manifest bytes changed, `--check` exit 1 |
| stale JSON contains invented public symbol | stale snapshot failure; graph still derives source membership |
| new type absent from stale classmap | discovered by AST scan, included in coverage |
| same FQCN in two package paths | owner collision, exit 2 |

Positive Api example:

```php
#[Api(since: '1.0')]
final readonly class TaskView
{
    public function __construct(
        public string $id,
        public TaskStatus $status,
    ) {}
}
```

Representative `php-api/1` record (all actual members sorted; this example is not a full
schema substitute — dossier §3.1 lists mandatory fields):

```json
{
  "schema": "php-api/1",
  "package": "axiomasoft/corex-tasks",
  "symbols": {
    "CoreX\\Tasks\\Task\\Data\\TaskView": {
      "kind": "class", "layer": "api", "since": "1.0",
      "final": true, "readonly": true,
      "extends": null, "implements": [],
      "properties": {"id": {"type": "string", "readonly": true}},
      "methods": {"__construct": {"parameters": [{"name": "id", "type": "string", "optional": false}]}},
      "constants": {}, "enum_cases": {}
    }
  }
}
```

First report on current source will have unclassified debt; exact count comes from new
scanner including supported metadata/PHPDoc, not assumed equal to 772. Successful report
exit 0 is not strict proof. After P04.2/P04.3 strict must show **zero** diagnostic records.

## 3. DTO and transaction seam

Before: tasks-workflow observer `saving(Task $task)` accesses status/id/isDirty.
After:

```php
#[Api(since: '1.0')]
final readonly class TaskCompletionCandidate
{
    public function __construct(
        public string $taskId,
        public TaskStatus $previousStatus,
        public TaskStatus $status,
        public bool $statusChanged,
    ) {}
}

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TaskCompletionCapture
{
    public function capture(TaskCompletionCandidate $candidate): void;
}
```

Task owner extracts scalars within existing `saving`/transaction path. Workflow listener
receives DTO and preserves receipt key/hash/TaskOperationReceipts logic. No post-commit
subscription. Existing `completion_capture.enabled` and missing WorkflowBinding behavior
preserved. Test scenarios: unchanged status => 0 receipts; Done transition => 1 receipt;
replay => same receipt; capture exception => task/receipt rolled back; disabled=>0;
no WorkflowBinding => normal task save, no receipt. A new task's previous status derives
existing initial TaskStatus behavior, never guessed by DTO constructor.

Other model transitions:

| Current reference | Replacement |
|:--|:--|
| Department model in access/tenancy queries | DepartmentRepository + existing Data/Department |
| Stage/Project class-string consumer metadata | public entity handle + StageView/ProjectView/read port |
| Account model in tenancy-owned config | own Internal/Models/Account; no foreign import |
| concrete `RegistryCompiler` from tools/operations | ModuleCompilation port; compiled result remains documented SPI |
| default DataScope authorizer referenced in external config/test | public owner factory in Testing/Laravel, returns DataScopeAuthorizer |

Before/after concrete paths and consumers — `public-by-usage-verdicts.tsv`.

## 4. Errors and strict cutover

```php
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ErrorCode
{
    public function value(): string;
}

#[Api(since: '1.0')]
enum OperationsErrorCode: string implements ErrorCode
{
    case ValidationFailed = 'operations.validation_failed';

    public function value(): string
    {
        return $this->value;
    }
}
```

Before `new ApiError('validation_failed', 422, 'Malformed JSON body.')`;
after `new ApiError(OperationsErrorCode::ValidationFailed, 422, 'Malformed JSON body.')`.
Registry definition code is ErrorCode object, wire descriptor remains stable string.
`urn:corex:problem:operations.validation_failed` is the associated type URI. Site category,
retryable/path/detail keys stay independently checked. Example boot negative tests:

- identical code contributed twice => `ERROR-DUPLICATE-CODE`, boot fails;
- webhooks provider contributes operations.foo => `ERROR-FOREIGN-PREFIX`, boot fails;
- different codes with one URI => `ERROR-DUPLICATE-TYPE`, boot fails;
- unregistered enum case => mapper/registry test fails;
- new ApiError literal alias/group-use/FQCN => `ERROR-LITERAL`, strict exit 1;
- corrupted persisted wire code => safe registered core.internal_error, no raw message;
- retryable true/false and JSON Pointer path/details boundary tested unchanged;
- code foo vs operations.foo => syntax invalid vs registered typed valid.

Fixed P04.4 commands:

```bash
scripts/php84 php scripts/php-api.php --report --format=json
scripts/php84 php scripts/php-api.php --write
scripts/php84 php scripts/php-api.php --strict --check
scripts/php84 php scripts/error-registry.php --check
scripts/php84 composer package-graph-gate
scripts/php84 composer golden
```

All final commands exit 0. `--write` twice yields zero manifest/doc diff; modifying one
snapshot byte yields exit 1 for `--check`, restoration yields 0. Product metadata after
removal example: `extra.corex` keeps schema/type/stability/namespace/module, loses only
`public-api`. All 20 package snapshots remain; product is consumer-only and receives no
application surface snapshot solely because it previously had namespace metadata.

`docs/architecture/semver-policy.md` matrix:

| Layer | Allowed consumer | Breaking example |
|:--|:--|:--|
| Api | call/read/catch | parameter rename, required field addition, enum value removal |
| Spi Implement | implement/extend documented members | new required method, changed protected hook |
| Spi Call | call/subscribe/configure declared seam | changed payload/constructor/data schema |
| Internal | owner implementation only | no compatibility promise, foreign import forbidden |

Roave negative fixtures preserve source dependencies and use base membership: deleted
old Api fails even if candidate manifest omits it; changing Internal class alone does not
enter public BC selection. Registry and manifests generated from source, not blessed JSON.
