# P00 — Примеры (design)

Иллюстрации формата. Значения — рекомендации `research/P00-design.md` §2; окончательные — решение исполнителя P00.1 (D25+).

## 1 `package-map.tsv` (фрагмент)

```tsv
old_dir	old_name	new_dir	new_name	new_root	type	phase
packages/core	corex/core	packages/core	axiomasoft/corex	CoreX	kernel	P02
packages/modules	corex/modules	packages/core	axiomasoft/corex	CoreX\Modules	kernel	P02
packages/operations	corex/operations	packages/operations	axiomasoft/corex-operations	CoreX\Operations	package	P02
packages/operations	corex/operations	packages/tools	axiomasoft/corex-tools	CoreX\Tools	package	P02
packages/operations	corex/operations	packages/webhooks	axiomasoft/corex-webhooks	CoreX\Webhooks	package	P02
packages/attributes	corex/attributes	packages/custom-fields	axiomasoft/corex-custom-fields	CoreX\CustomFields	package	P02
packages/access	corex/access	packages/access	axiomasoft/corex-access	CoreX\Access	package	P02
packages/crm	flex/crm	products/flex-crm	flex/crm	Flex\Crm	product	P02
packages/import-export	—	—	—	—	tombstone-remove	P02
```

## 2 `namespace-map.tsv` (фрагмент: folding operations, зоны ядра, переименования)

```tsv
old_package	old_path	old_prefix	new_package	new_prefix	phase	note
corex/operations	packages/operations/src/Definition/	CoreX\Api\	axiomasoft/corex-operations	CoreX\Operations\Definition\	P02	F9 engine
corex/operations	packages/operations/src/Definition/Contracts/	CoreX\Api\Contracts\	axiomasoft/corex-operations	CoreX\Operations\Definition\Contracts\	P02	namespace = путь
corex/operations	packages/operations/src/Tools/	CoreX\Api\	axiomasoft/corex-tools	CoreX\Tools\	P02	F9 split
corex/operations	packages/operations/src/Tools/Contracts/	CoreX\Api\Contracts\	axiomasoft/corex-tools	CoreX\Tools\Contracts\	P02	F9 split
corex/operations	packages/operations/src/Webhooks/Contracts/	CoreX\Api\Contracts\	axiomasoft/corex-webhooks	CoreX\Webhooks\Contracts\	P02	F9 split
corex/core	packages/core/src/Tenancy/	CoreX\	axiomasoft/corex	CoreX\Context\	P02	F2 освобождает CoreX\Tenancy
corex/core	packages/core/src/Money/	CoreX\	axiomasoft/corex-intl	CoreX\Intl\Money\	P02	F3
corex/core	packages/core/src/SavedViews/	CoreX\	axiomasoft/corex-panels	CoreX\Panels\SavedViews\	P02	F4
corex/core	packages/core/src/	CoreX\	axiomasoft/corex	CoreX\	P02	остаток ядра (longest-match)
corex/core	packages/core/tests/Support/	CoreX\Testing\	axiomasoft/corex	CoreX\Testing\	P02	reserved segment
corex/modules	packages/modules/src/	CoreX\Modules\	axiomasoft/corex	CoreX\Modules\	P02	F1 merge
corex/attributes	packages/attributes/src/	CoreX\Attributes\	axiomasoft/corex-custom-fields	CoreX\CustomFields\	P02	F7
corex/access	packages/access/src/	CoreX\Auth\	axiomasoft/corex-access	CoreX\Access\	P02	F10
corex/search-meilisearch	packages/search-meilisearch/src/	CoreX\Search\Meilisearch\	axiomasoft/corex-search-meilisearch	CoreX\SearchMeilisearch\	P02	F11
```

Longest-match: `packages/core/src/Tenancy/Contracts/TenancyManager.php` → строка `packages/core/src/Tenancy/`; `packages/core/src/Settings/…`
→ `packages/core/src/` (остаток ядра). Строка `packages/operations/src/` без подкаталога не нужна, если все подкаталоги и
корневые файлы покрыты; корневые файлы `src/*.php` (`ApiServiceProvider.php`) покрываются строкой `packages/operations/src/`.

Файловая строка (плоский `packages/core/src/Contracts/`, зона — по `CONTRACT_ZONE` в `zone-consumers.py`):

```tsv
corex/core	packages/core/src/Contracts/SavedViewRepository.php	CoreX\	axiomasoft/corex-panels	CoreX\Panels\SavedViews\Contracts\	P02	F4, файловая строка
```

## 3 `check-maps.py` — worked examples

| Вход | Вывод | exit |
|:--|:--|:--|
| Полные карты | `maps OK: <N> prefixes, <M> packages` | 0 |
| Удалена строка `packages/operations/src/Tools/` | `uncovered: packages/operations/src/Tools/ToolServiceProvider.php (…)` (все файлы каталога) | 1 |
| Строка `…src/Webhooks/` c `new_prefix=CoreX\Tools\Webhooks\` | `root-collision: CoreX\Tools -> axiomasoft/corex-tools, axiomasoft/corex-webhooks` | 1 |
| Строка с `old_path=packages/panels/src/Legacy/` (каталога нет) | `dead-row: packages/panels/src/Legacy/` | 1 |
| Ядро владеет `CoreX\Ledger` вне §Kernel modules | `foreign-root: CoreX\Ledger (axiomasoft/corex) not in kernel modules` | 1 |
| `--doctrine`, `PKG-003` отсутствует в delta | `doctrine-missing: PKG-003` | 1 |
| `--doctrine`, `API-004` дважды | `doctrine-duplicate: API-004` | 1 |

Список §Kernel modules скрипт читает из glossary: строки таблицы `| CoreX\<Module> | … |` раздела `## Kernel modules`.

## 4 Glossary — формат строк

```markdown
## Packages
| Каталог | Composer | Namespace | Тип | Назначение |
|:--|:--|:--|:--|:--|
| `packages/core` | `axiomasoft/corex` | `CoreX\` | kernel | Ядро платформы: модули, контекст исполнения, настройки, сообщения, ошибки и конфигурация для любых приложений. |

## Kernel modules
| Module | Назначение | Источник |
|:--|:--|:--|
| `CoreX\Context` | Контекст исполнения (account, workspace, актор) и SPI его жизненного цикла. | `core/src/Tenancy` (F2) |

## Identifiers per package
| Пакет | config | publish-tags | env | artisan |
|:--|:--|:--|:--|:--|
| `axiomasoft/corex-webhooks` | `corex-webhooks` | `corex-webhooks-config`, `corex-webhooks-migrations` | `COREX_WEBHOOKS_*` | `corex:webhooks:<verb>` |
```

## 5 `doctrine-delta.md` — формат строки (P00.2)

```markdown
| Документ | ID | Действие | Новое правило (одно предложение) | Фаза | Гейт |
|:--|:--|:--|:--|:--|:--|
| constitution.md | PKG-003 | rewrite | `axiomasoft/corex` не зависит ни от одного пакета монорепо и не знает конкретных типов пакетов (cast → SPI). | P04 | ArchitectureTest kernel-deps |
| constitution.md | API-001 | rewrite | Публичная поверхность — символы с `#[Api]`/`#[Spi]`; `extra.corex.public-api` удаляется. | P04 | api-attributes gate |
| constitution.md | PKG-009 | remove | Тумбстоун `import-export` удалён в P02. | P02 | package-map check |
| constitution.md | STR-003 | keep | — | — | — |
| naming.md | NEW-1 | add | Namespace пакета = путь, один корень `CoreX\<Pascal>` на пакет. | P02 | check-maps + composer psr-4 gate |
```

## 6 P00.3 — before/after

`plans/ACTIVE.md`:
```diff
-**Active:** [PLAN2 — COREX-PLATFORM-HARDENING](2026.09.19-№1-COREX-PLATFORM-HARDENING/plan.md)
-**Updated:** 2026-09-19
+**Active:** [PLAN3 — COREX-KERNEL](2026.09.27-№1-COREX-KERNEL/plan.md)
+**Updated:** 2026-09-27
+
+**Paused (superseded):** [PLAN2 — COREX-PLATFORM-HARDENING](2026.09.19-№1-COREX-PLATFORM-HARDENING/plan.md) — PLAN3.D23.
```

PLAN2 `plan.md` Meta:
```diff
-| Paused By | — |
+| Paused By | PLAN3 `2026.09.27-№1-COREX-KERNEL` (D23, 2026-09-27) |
```

PLAN2 `handoff.md` (Done/Remaining/Sources сохраняются):
```diff
-**Next:** waiting: определить producer scope для исправления DOD-24/25; P17.12 не готов.
+**Next:** waiting: PLAN2 superseded by PLAN3 (`plans/2026.09.27-№1-COREX-KERNEL`, PLAN3.D23); закрытие — решение владельца после релиза PLAN3.
```
