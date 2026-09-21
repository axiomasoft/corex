# Внешние подтверждения фазы P2 — 2026-09-19

Сводка пяти запросов детализации P2 с привязкой к параграфам `P2-design.md`.
Сырые ответы и точные строки запросов — `../artifacts/P2-design-rag/`
(`manifest.json` несёт статусы, первичные источники и SHA256 файлов захвата).

Правило чтения: `RAG:✅` — внешний источник подтверждает **названную общую практику**.
Вывод «поэтому в CoreX так» — всегда локальный (`RAG:—`), из кода и измерений.
Ни один ответ не является спецификацией CoreX.

## 1. Что подтвердилось и где это применено

| ID | Что подтверждено | Первичный источник | Применено в |
|:--|:--|:--|:--|
| P2-Q1 | `token_get_all` отдаёт плоский поток лексем, не дерево и не резолв имён; `TOKEN_PARSE` не делает его AST | php.net/token-get-all · php.net/book.tokenizer | `P2-design.md` §3, D16 |
| P2-Q1 | PHP-Parser + NameResolver резолвит alias, group-use и текущий namespace; нерезолвимые function/const fallback — сознательный предел статики | github.com/nikic/PHP-Parser · Name_resolution.markdown | `P2-design.md` §3 (колонка `certainty`) |
| P2-Q1 | Строковый FQCN и динамическое имя — convention/unresolved, не compiler-proof зависимость | тот же | `P2-design.md` §2.1, §4.3 |
| P2-Q1 | Для инвентаря достаточно узкого разбора грамматики `use`, если закрытый набор конструкций назван явно | синтез поверх php.net + PHP-Parser | D16: stdlib + UseTable, без `require-dev` |
| P2-Q2 | Три графа отвечают на разные вопросы: Composer — что объявлено; импорт — что код знает; контейнер/provider — что связывается | getcomposer.org/doc/04-schema.md · laravel.com/docs/12.x/providers · laravel.com/docs/12.x/packages | `P2-design.md` §4 |
| P2-Q2 | Графы не обязаны совпадать ребро-в-ребро; необъяснённое расхождение — долг, объяснённое (composition root, allowlist) — нет | синтез + Symfony Flex / Laravel discovery | `P2-design.md` §4.4 `G-EXPLAINED` |
| P2-Q2 | Скрытая связь — строки, `app()`, morph, event names, `extra.laravel`; их надо инвентаризировать отдельно, а не притворяться `use` | laravel.com/docs/12.x/packages · laravel.com/docs/12.x/container | `P2-design.md` §4.3 закрытый набор сайтов |
| P2-Q2 | Flex/`extra.laravel` доказывают, что установка пакета ≠ runtime-граф: discovery и рецепты — отдельная проекция | github.com/symfony/flex · laravel.com/docs/12.x/packages | `P2-design.md` §4.3 пункт 3 |
| P2-Q3 | Владение таблицей — у возможности, не у команды и не у префикса имени | shopify.engineering (componentization) | `P2-design.md` §5, шапка `sys_` |
| P2-Q3 | Writers и readers — разные риски и разные поля каталога | shopify.engineering · Spring Modulith fundamentals | `P2-design.md` §5.1 |
| P2-Q3 | `contains_pii: true` слишком слабо; нужен класс и, где возможно, поле | Spring Modulith canvas + практика каталога | `P2-design.md` §5.2; ненаблюдаемое = `unobserved` |
| P2-Q3 | Restore — не «у нас есть PITR», а что происходит с стёртыми PII после восстановления | синтез поверх тех же источников | `P2-design.md` §5.1 `restore_policy` |
| P2-Q3 | Upgrade по умолчанию — expand → migrate → switch → contract | Spring Modulith + практика миграций | `P2-design.md` §5.1 `policy-default` |
| P2-Q4 | Laravel 12 не даёт per-key метаданных secret/reloadable/deprecated; каталог обязан быть своим | laravel.com/docs/12.x/configuration | `P2-design.md` §6, D15 |
| P2-Q4 | `env()` только в config-файлах; после `config:cache` значение — факт деплоя | laravel.com/docs/12.x/configuration | `P2-design.md` §6.2 `deployment` / `reloadable=no` |
| P2-Q4 | Symfony Config Definition — референс схемы (тип, default, deprecated), не зависимость | symfony.com/doc/7.0/components/config/definition.html | форма колонок §6.1 |
| P2-Q4 | OptionsResolver — граница массива опций; типизированный объект опций строится отдельно | symfony.com/doc/7.0/components/options_resolver.html | колонка `typed_options_field`; сегодня `—` |
| P2-Q5 | Fitness function называет свойство, измерение, порог и реакцию; это не «оценка качества вообще» | nealford.com · thoughtworks fitness-function-driven development | форма scorecard §8 |
| P2-Q5 | Средний балл запрещён: нельзя компенсировать дыру в данных чистыми именами | thoughtworks (не KPI / не лидерборд) | `P2-design.md` §0 п.11, §8 |
| P2-Q5 | Конфигурация и публичный API — тоже контракт; SemVer сам по себе его не проверяет | laravel.com/docs/packages · packagist.org/about | критерии `config` и `bc-policy` |
| P1-Q4 | Каталог владения: владелец, миграции, классификация, потребители, FK-исключения, retention, PII | shopify.engineering (захват P1) | набор полей §5; не дублируется запросом сверх P2-Q3 |

## 2. Что НЕ подтвердилось внешне

| Пробел | Статус | Как несёт дизайн |
|:--|:--|:--|
| Обязательность `nikic/php-parser` именно как Composer-зависимости инвентаря | не требуется источником | D16 выбирает stdlib: источник требует резолва имён, не конкретного пакета |
| Универсальная встроенная схема ключей Laravel (secret/reloadable) | отсутствует в фреймворке | собственный каталог §6; это подтверждённое *отсутствие*, не `[UNVERIFIED]` |
| Числовой порог «хорошего» scorecard | источник прямо против KPI | порогов-баллов нет; есть детерминированные 0/1/2 и отчёт неоднородности |

## 3. Дефект провайдера и таймаут

Известный дефект P1 (ответ, начинающийся с таблицы, усекается) на этой фазе **не
повторился**: все полученные ответы начаты прозой по явной просьбе в запросе.

P2-Q3 с первой попытки завершился таймаутом MCP (`-32001`). Повторный запрос прошёл
и дал прозу плюс первичные URL Shopify / Spring Modulith. В `manifest.json` первая
попытка зафиксирована как `timeout`, вторая — `success`. Пункт, который должен был
подтвердить каталог таблиц, **не** несёт `[UNVERIFIED]`: повтор успешен, плюс
наследование P1-Q4.

## 4. Что внешнее подтверждение изменило в форме фазы

**(1) P2-Q1 → D16.** Эскиз допускал `nikic/php-parser` как `require-dev`. Источник
требует резолва имён, а не конкретной библиотеки. Сканер плановый и не должен
менять корневой Composer. Закрытый набор конструкций + колонка `certainty` закрывают
тот же риск, что и AST, ценой явного `unresolved` на динамике.

**(2) P2-Q2 → §4.4.** Эскиз называл расхождение «самостоятельным результатом».
Источник уточняет: расхождение composition root и allowlist — ожидаемая форма
инверсии зависимостей. Поэтому вводится код `G-EXPLAINED`, иначе P3 получит шум.

**(3) P2-Q4/Q5 → D15 и §8.** Каталоги и scorecard — evidence/fitness functions, не
нормативный прозаический слой и не KPI. Отсюда запрет колонки `verdict` в `files.csv`
и запрет среднего балла.
