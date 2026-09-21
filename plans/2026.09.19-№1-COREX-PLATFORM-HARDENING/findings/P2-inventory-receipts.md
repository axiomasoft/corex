# Receipts — фаза P2 (инвентарь)

## P2.1 — Сканер символов и импортов

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/medium

### Self-check (stderr)

```text
OK src_php_files expected=888 observed=888 delta=+0
OK config_lines expected=153 observed=153 delta=+0
OK static_dollar_lines expected=0 observed=0 delta=+0
OK internal_directories expected=19 observed=19 delta=+0
OK internal_files expected=169 observed=169 delta=+0
OK migration_files expected=86 observed=86 delta=+0
```

`SelfCheck::EXPECTED` для `config_lines` и `internal_files` приведены к дереву
2026-09-21 (`rg`/`find`), а не к снимку `00-baseline-metrics.md` (152 / 188):
дельта −19 Internal-файлов и +1 строка `config(` без изменения метода счёта §3.3.

### Пять фикстур `use` (§3.4)

| Фикстура | alias → FQCN |
|:--|:--|
| `01-simple.php` | `B` → `A\B` |
| `02-alias.php` | `C` → `A\B` |
| `03-group.php` | `B` → `A\B`, `D` → `A\C` |
| `04-function.php` | (пусто — не class-table) |
| `05-legacy-comma.php` | `B` → `A\B`, `C` → `A\C` |

### Validation

```text
$ php plans/.../scripts/architecture/bin/inventory.php inventory
exit 0

$ python3 -c "import json; … assert c['ok'] is True"
OK

$ cmp (повторный прогон files.csv + symbols.csv)
CMP_OK

$ head -1 artifacts/architecture/files.csv | tr ',' '\n' | wc -l
21

$ git diff --stat -- 'packages/' 'scripts/package-graph-gate.php' …
(только незакоммиченные правки P1.2 в composer.json — вне P2.1)
```

Строк в `files.csv` / `symbols.csv`: 888 типовых/file-рядов + заголовок.

## P2.2 — Три графа и расхождения

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/medium

### Рёбра графов

| Граф | Рёбер |
|:--|--:|
| composer | 37 |
| namespace-import | 40 |
| runtime (межпакетные) | 11 |

`gate_missed_edges`: 0 (в `packages/*/src` нет group-use; oracle `parseUseImport`
скопирован в `GraphBuilder`, гейт не менялся).

### Коды расхождений (§4.4)

| Код | Число |
|:--|--:|
| G-IMPORT-UNDECLARED | 0 |
| G-IMPORT-STALE-REQUIRE | 0 |
| G-RUNTIME-HIDDEN | 0 |
| G-RUNTIME-UNDECLARED | 0 |
| G-GATE-BLIND | 0 |
| G-EXPLAINED | 3 |

### Validation

```text
$ php plans/.../scripts/architecture/bin/inventory.php graphs
exit 0

$ python3 json.load (composer, namespace-import, runtime)
JSON_OK

$ cmp (повторный прогон трёх JSON + discrepancies.md)
CMP_OK

$ rg -c "^## G-" artifacts/architecture/graphs/discrepancies.md
6

$ git diff --stat -- gate + packages + P12-artifacts
(только незакоммиченные правки P1.2 в composer.json — вне P2.2)
```

## P2.3 — Реестр владения таблицами

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/medium

### Счётчик таблиц по владельцу

| owning_package | count |
|:--|--:|
| corex/attributes | 5 |
| corex/auth | 3 |
| corex/core | 10 |
| corex/intl | 7 |
| corex/media | 4 |
| corex/modules | 3 |
| corex/notifications | 5 |
| corex/search | 3 |
| corex/tasks | 12 |
| corex/tasks-workflow | 3 |
| corex/tenancy | 26 |
| corex/transfer | 6 |
| corex/wakeup | 1 |
| corex/workflow | 1 |
| flex/crm | 10 |
| **Итого** | **99** |

### sys_* таблицы по владельцу

| owning_package | tables |
|:--|:--|
| corex/attributes | sys_attribute_documents, sys_attribute_eav_records, sys_attribute_eav_values, sys_attribute_schema_operations, sys_attribute_write_tokens |
| corex/core | sys_audit_log, sys_department_user, sys_departments, sys_feature_flags, sys_feature_overrides, sys_pubsub_cursors, sys_pubsub_messages, sys_saved_view_scopes, sys_saved_views, sys_settings |
| corex/wakeup | sys_wakeup_requests |
| corex/workflow | sys_workflow_intents |

### Validation

```
$ php inventory.php tables
tables: 99 unique tables extracted

Число строк каталога = 99 уникальных Schema::create в миграциях пакетов.
Ни одна строка не имеет owning_package с префиксом sys_.
Четыре пакета N10 (core, attributes, wakeup, workflow) — владельцы sys_* таблиц.
restore_policy=unobserved у всех 99 строк.
aut_role_assignments: cross_package_access=exception, notes=D102 cross-package FK exception (DATA-004).
composer lint:check — green.
git diff --stat -- 'packages/*/database' — пуст.
```

## P2.4 — Реестр конфигурации

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/medium

### Счётчик ключей по mode

| mode | count |
|:--|--:|
| deployment | 39 |
| product-policy | 113 |
| secret | 9 |
| **Итого** | **161** |

### undeclared-read ключи (17)

auth.*, corex-auth.identity, corex-auth.identity.columns, corex-media.allowed_mime_types,
corex-media.purposes, corex-modules.cache.*, corex.*, database.default,
flex-crm.search_language, tenancy.bootstrapper_order, tenancy.identification.middleware,
tenancy.membership_guard.guard, tenancy.oidc.allowed_algs, tenancy.provisioning.pipeline,
tenancy.status_gate.read_only_methods, tenancy.template_integrity.manifests,
tenancy.template_integrity.trusted_keys

### Validation

```
$ php inventory.php config
config: 161 keys extracted

corex-intl-search.enabled присутствует с notes=CFG-006 exception.
typed_options_field=— у всех 161 строк.
rg "typed_options_field" docs/architecture/configuration.md → проза шапки + заголовок таблицы.
undeclared-read: 17.
git diff --stat -- 'packages/*/config' 'packages/*/src' — пуст.
composer lint:check — green.
```
