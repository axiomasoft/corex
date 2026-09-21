---
id: D93
date: 2026-09-20
status: accepted
item: P16.9
items: [P16, P16.9]
supersedes: []
superseded_by: null
---
# D93 — `corex/panels` сохраняет имя и компактный профиль

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:✅ `research/P16-design-rag.md` §2; RAG:— `packages/panels/README.md`
и 11 файлов `packages/panels/src`.

## Решение

Сохранить package identity `corex/panels`, namespace `CoreX\Panels` и профиль A без
структурных переносов. Термин означает headless-набор именованных entity-linked UI
поверхностей, а не обязательный admin shell. README обязан удерживать эту границу: пакет не
владеет routes, widgets, rendering, persistence или command execution.

## Почему

Filament и MoonShine используют panel для агрегирующей UI-оболочки, Nova — Resource и
Dashboard, Orchid — Screen, Backpack — admin/CRUD panel. Единого внешнего термина нет;
переименование в `admin`, `dashboard`, `screens` или `resources` сузило бы либо перепутало
текущий нейтральный контракт. При 11 файлах новый уровень каталогов нарушил бы STR-004.

## Consequences

P16.9 документирует пять сравнений и доказывает отсутствие move. Новые panel concepts не
добавляются этой фазой.
