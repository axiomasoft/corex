# Независимый design audit — P00–P05 + P10

Дата: 2026-10-01. Аудитор: `codex/gpt-6.1-sol/xhigh`, новая сессия по PLAN3.D79/PLAN3.D80.
Фактический маршрут подтверждён provider-written turn context; evidence —
`artifacts/P05-P10-design-reaudit/route-evidence.json`.
Fingerprint finish: `e7104b716f0ffdac0dd3c8446ce7af54d2edea7c07f373ac6b5ff42ca9748d19`.

**Вердикт: GREEN** — 0 Blocker, 0 Major, 1 Minor в примере будущей реализации.
Дизайн допускает последовательное исполнение строк 14–20 execution-sheet. Runtime и hosted CI этим аудитом
не сертифицированы; P10.1–P10.4 и P05.1–P05.4 остаются неисполненными.

## Minor m1 — неверный тип корневого `handlers` молча превращается в пустую конфигурацию

Место: `artifacts/P05-design/examples.md:47–48`, образец `WakeupOptions::fromArray`.
Условие `is_array($config['handlers'] ?? null) ? $config['handlers'] : []` пропускает цикл для строки или числа
и не добавляет нарушение. Воспроизведённый результат: `['handlers' => 'broken']` и `['handlers' => 42]`
принимаются с `handlers=[]`. Если этот образец перенести буквально, неверная конфигурация выключит handlers
вместо отказа загрузки. Требование формы принято в PLAN3.D69 п.2 и Implementation Rules P05.1.

Воспроизведение без изменений продукта и без БД:

```bash
python3 'plans/2026.09.27-№1-COREX-KERNEL/artifacts/P05-P10-design-reaudit/reproduce-wakeup-options.py'
```

Сырой вывод — `artifacts/P05-P10-design-reaudit/wakeup-options-repro.log`.
Исправление в owning P05.1: отличать отсутствующий ключ от заданного значения неверного типа; во втором случае
добавлять нарушение `handlers` до `failIfAny()`. Тесты Options дополняются этими входами.
Точный выбор архитектуры уже принят: новый D#, перепроектирование и новый audit не требуются для этой реализации.
Замечание не блокирует дизайн: преамбула examples объявляет тела методов скелетами, а P05.1 уже требует проверку
формы. Следующий item P10.1 этот пример не потребляет. Дизайн и скелет в текущем аудите не редактировались.

## Delta предыдущего аудита

Исторический ATTENTION `findings/P05-P10-design-audit.md` относится к fingerprint `f188df5f…`.
Проверены исправления PLAN3.D76 и уточнения PLAN3.D78:

| Замечание | Текущий результат |
|:--|:--|
| A1, агрегатор P10.1 | Проверяется проход блока access; success всего агрегатора принадлежит P10.4. Критерии достижимы по порядку items. |
| A2, Files/CHANGELOG | P10.2 включает CHANGELOG пакетов, CoreZoneTest и ErrorContractTest; P05.3 включает CHANGELOG при изменении манифеста. |
| A3, локальный рецепт | test:hermetic определяется в P10.1; рецепт устанавливает root/access, применяет Pest ^4.7 для Laravel 12, передаёт --with массивом и проверяет committed HEAD. |
| A4, Validation P05.3 | Явные glob-pathspec исключают мост; сравнение числа Connection-строк по файлам заменяет счёт добавленных строк diff. |
| m1–m3, hosted CI/review | D76 разделяет event-skipped и failure, проверяет CI на каждом P05 item; для продуктового ремонта P10.4 прописана отдельная проверка. Последующее указание владельца об отмене SKM review сохранено отдельно. |
| m4–m6 | RuntimeConfigCheck может читать Options; SavedViewsRegistrar назван; fallback V3 имеет явное исключение в приёмке. |

Новых материальных замечаний к D78 не найдено. Проверены: сохранение собственных members и внутрипакетного
наследования, раздельные extends/implements/uses, AST preimage без vendor-членов; раздельные startup/validation
бюджеты worker, ограниченная запись, framing, shape validation, stderr draining и bounded cleanup; исполнение
перенесённых PG-методов в PG-лейне и недоступный адрес только для процесса test:hermetic.
Это проверка контрактов и исходного кода, не утверждение об уже реализованном поведении.

## Покрытие и доказательства

- Текущий finish: 7 объявленных фаз, 25 items, 20 execution-sheet admissions. Единственный нормативный Routing
  разобран штатным compiler; группы, максимальные route/review и зависимости совпадают с sheet.
- P00–P04 остаются terminal GREEN. Их локальная evidence переиспользована; продуктовые пути байт-идентичны базе
  P04 `39ae489a`. Старый локальный GREEN не превращён в current hosted GREEN: долг H1–H12 принадлежит P10.
- P05 и P10: проверены phase/item contracts, dossier, examples, ссылки на факты/captures, Files, Validation,
  Golden/BC boundaries и допустимые отложенные результаты предшественников. P05 стартует после закрытия P10.
- `plan-lint`: exit 0, 0 ошибок, одно историческое предупреждение P04 о workflow-прозе.
- Сухой рендер bundle P10.1: 20 разрешённых entries. Будущие producer-dependent bundles не создавались.
- `bash -n` для normative ci-local.sh: exit 0. Скрипт не исполнялся; установки и CI-матрица принадлежат исполнению.
- Новых внешних решений нет: использованы сохранённые первичные captures и исполнимые VERIFY-AT-EXEC границы.
- Продукт не менялся: до/после проверен digest всех 2637 доступных tracked файлов вне plans; авторский fingerprint
  также не изменился. Phase close и продуктовая реализация не выполнялись.

Machine evidence: `artifacts/P05-P10-design-reaudit/static-validation.json`, `routing-compiled.json`,
`P10.1-bundle-dry.json`, `product-before.json`, `product-after.json`, `design-snapshot-before.json`,
`design-snapshot-after.json`, `plan-lint-before.log`, `plan-lint-final.log`.

## Runtime fallback

Незакоммиченный Task runtime в соседнем репозитории заменил русские заголовки на английские и отвергал этот
принятый план, включая Routing. Это воспроизведённый процедурный дрейф, не дефект продукта или дизайна.
Использована неизменённая committed версия Task `ba841d9a4a3b629e950db5b4dcfacbd102872189`, извлечённая через
`git archive`; чужое дерево не изменялось. На ней compiler и lint проходят. Provenance — `runtime-provenance.json`.
Для новой сессии при сохранении этого дрейфа воспроизводится тот же fallback; форматы плана не переводятся ради
незакоммиченного инструмента.

## Продолжение и контекст

Мелкое исправление m1 можно выполнить в текущем контексте, но оно принадлежит будущему P05.1 и не препятствует
следующему P10.1. Рекомендация: передать исполнение в новую сессию, начиная со строки 14 — P10.1.

Это качественная оценка, а не измерение заполнения окна: provider usage/context telemetry недоступна,
процент и оставшиеся токены не заявляются. Аудит уже загрузил контракты обеих фаз, исторические решения,
досье и несколько участков runtime; впереди работа над CI и продуктом с длительной обратной связью.
Продолжение всей этой работы увеличит вероятность compact до её завершения и потери локальных деталей.
Свежая сессия прочитает только bundle выбранного item; проверенные выводы остаются в репозитории.
Недостаточность оценена для предстоящего workset, не для завершения самого аудита; точный момент compact неизвестен.

Указание владельца «при исполнении игнорировать проверки ревью от skm» сохранено в
`artifacts/P05-P10-design-reaudit/owner-direction.md` и handoff. Выполнение runtime validation и hosted CI остаётся
обязательным. Новая сессия и исполнение этим аудитом не запускались.
