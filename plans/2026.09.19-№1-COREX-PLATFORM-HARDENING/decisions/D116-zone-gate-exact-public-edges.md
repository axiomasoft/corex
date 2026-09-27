---
id: D116
date: 2026-09-22
status: accepted
item: P6.2
items: [P6, P6.2]
supersedes: []
superseded_by: null
---

# D116 — Шесть публичных рёбер зон остаются поимёнными, матрица не открывается

**Actor:** plan-exec/cursor-grok-4.7/high

**Evidence:** `RAG:—` чистый прогон R-Z1 на живом дереве называет ровно шесть
импортов. `DataScope::fromTenantContext` и поле `DomainEvent::$tenant`
используются пакетами монорепозитория. `FeatureFlags` принимает `SettingsScope`
в той же сигнатуре, что и `DatabaseFeatureFlags`. D32 запрещает перенос FQCN
в P6. D109 оставил фабрику `fromTenantContext` после скалярного конструктора.

## Решение

`ALLOWED['Foundation']` остаётся пустым. Features не получает зону Settings.
Шесть импортов разрешены только как точное совпадение файла и символа:

- `packages/core/src/Support/Data/DataScope.php` → `CoreX\Tenancy\TenantContext`
- `packages/core/src/Events/DomainEvent.php` → `CoreX\Tenancy\TenantContext`
- `packages/core/src/Events/DomainEvent.php` → `CoreX\Tenancy\Contracts\TenantContextResolver`
- `packages/core/src/Events/FeatureFlagToggled.php` → `CoreX\Contracts\SettingsScope`
- `packages/core/src/Features/DatabaseFeatureFlags.php` → `CoreX\Contracts\SettingsScope`
- `packages/core/src/Features/DatabaseFeatureFlags.php` → `CoreX\Enums\SettingScope`

Любой другой символ на этих файлах и любой новый файл остаются нарушением R-Z1.
Посадка Foundation → `Views\Internal` и неизвестный `Support/Probe.php` остаются
красными.

## Почему

Снятие этих рёбер меняет публичные сигнатуры сразу в нескольких пакетах. Это
не локальная правка гейта. Открыть Foundation на весь Tenancy или Features на
весь Settings было бы шире измеренного факта.

## Consequences

P6.2 записывает список в `CoreZoneTest` и в `docs/architecture/core-zones.md`.
Перенос или удаление этих методов — отдельный item под G-API, не молчаливое
расширение матрицы.
