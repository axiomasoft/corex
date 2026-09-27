# PLAN3 P00 — исправления design audit

Автор исправлений: codex/gpt-6-astra/high. Scope: готовность дизайна P00 к исполнению;
P01–P09 остаются rolling backlog по D21/D24. Исходный ATTENTION и его доказательства сохранены.

| Finding | Исправление | Проверка |
|:--|:--|:--|
| A1 | Целевой dependency-edges с обоими направлениями, projected endpoints, конкретными resolutions snapshot/provider/Money/SavedViews; обязательная развязка до split P02 | Контракт `--dependencies`: coverage, no cycle, no kernel dependency, no foreign Internal; frontier-решения остаются результатом P00.1 |
| A2 | Запрет src/ fallback, точные root files, ownership конечных FQCN, явные dev/root symbols и namespace-less файлы | Негативный fixture Tools остаётся uncovered; positive examples согласованы |
| A3 | Doctrine по ключу (документ, ID), отдельная проверка policy/NEW; architecture command с корректными exit codes | Смешанная таблица допускается; valid/invalid/missing architecture fixtures |
| A4 | Готовый `check-supersede.py`: execution baseline один раз, далее точечная проверка полей и сохранности остального PLAN2 | Positive delta; negative protected carrier / unrelated text / missing condition |
| A5 | Пересчитаны zone-consumers, Tenancy 414 и Storage 37 | `zone-consumers.py --check` exit 0 |

Дополнительно: обязательный chatom read сохранён в переносимом source-linked снимке;
B00-A использует текущий Codex gpt-6-astra/high и независимый Cursor grok-4.7/high,
без понижения семантического маршрута. Claude CLI вернул expired OAuth, его отказ сохранён
как неуспешный запуск, а не review verdict.

Локальные результаты: `design-remediation-checks.json`. `plan-lint` и `finalize-design --finish`
успешны. P00.1 bundle dry-render успешен; P00.2 ожидает артефакты P00.1 по контракту.
`check-maps.py` и конечные карты — будущие deliverables P00.1, они не выдаются за реализованные.

Независимый вердикт и финальный fingerprint фиксируются в `design-remediation-independent-review.json`
и append-only journal. Итоговый допуск проверяется `plan-work.py design-gate`.

Итог: **GREEN**, независимый reviewer `cursor/grok-4.7/high`, fingerprint `e8524b9f64353b897decff9a8246fa85c39ae888a7a244fefe4369c3979b40c2`.
A1–A5 закрыты; исполнение P00 разрешается design gate.
