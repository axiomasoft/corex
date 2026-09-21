# Внешние подтверждения фазы P7 — 2026-09-19

Сводка пяти запросов детализации P7 с привязкой к пунктам `P7-design.md`.
Сырые ответы и точные строки запросов — `../artifacts/P7-design-rag/`
(`manifest.json` несёт статусы, первичные источники и SHA256 файлов захвата).

Правило чтения: `RAG:✅` — внешний источник подтверждает **названную общую практику**.
Вывод «поэтому в CoreX так» — всегда локальный (`RAG:—`), из кода и измерений.
Ни один ответ не является спецификацией CoreX.

## 1. Что подтвердилось и где это применено

| ID | Что подтверждено | Первичный источник | Применено в |
|:--|:--|:--|:--|
| P7-Q1 | `fromArray(array)` не вызывает `config()`; сервисы получают readonly DTO | Laravel packages · Config repository | D39, шаблон §3 |
| P7-Q1 | `config()` уместен в service provider и artisan-команде, не в middleware/репозитории | Laravel packages · configuration | CFG-002, P7.11 |
| P7-Q1 | OptionsResolver валидирует массив, не заменяет типизированный объект | Symfony OptionsResolver 7 | эскиз принят; библиотека не вводится |
| P7-Q2 | `__toString` и `__debugInfo` закрывают интерполяцию и `var_dump` | php.net magic methods | `SecretRef`, D40 |
| P7-Q2 | `reveal()` — явная граница; `SensitiveParameter` редактирует backtrace, не логи | php.net SensitiveParameter | D40, P7.1/P7.10 |
| P7-Q2 | `json_encode` / `serialize` — отдельные каналы; plaintext туда не отдаётся | php.net | `SecretRef` без JSON |
| P7-Q3 | Невалидная форма и неизвестный драйвер — отказ при boot/compile | Symfony Config · Laravel config:cache | D40, `fromArray` |
| P7-Q3 | Команда diagnose печатает драйвер и адаптеры, никогда секреты | Laravel configuration | P7.10 |
| P7-Q3 | Живая досягаемость внешней системы — readiness, не boot | Symfony extensions | Scope Excluded команды |
| P7-Q4 | Несколько связанных ключей — immutable policy, не четыре `config()` | Fowler · Microsoft Options · Laravel middleware | D41, P7.2/P7.3 |
| P7-Q4 | Options bag ≠ policy: bag хранит значения, policy отвечает на правило | Microsoft Options | D41 |
| P7-Q5 | Fitness-function с посадкой, называющей файл; observational ≠ blocking | ThoughtWorks · Pest Arch | P7.11, инвариант негатива |

## 2. Что НЕ подтвердилось внешне

| Пробел | Статус | Как несёт дизайн |
|:--|:--|:--|
| Обязательность Options на опубликованный файл без `src`-чтений | источника «каждый config.php = класс» нет | D38: Options у читателей |
| Что статический holder над immutable snapshot запрещён на Octane | Q10 запрещает *мутацию* конфига и собственные глобали per-request; deployment-снимок процесса не меняется | D39: E-CONFIG-HOLDER |
| Что Symfony OptionsResolver обязан быть runtime-зависимостью | компонент опционален как движок фабрики | не вводится (запрет новых runtime-зависимостей) |
| Численный порог «достаточно ключей для policy-класса» | внешней нормы нет; Q4 говорит «есть cross-field инвариант или несколько потребителей» | D41: три policy tenancy, `panel` — скаляр |

## 3. Дефекты провайдера и транспорта

Таймаут MCP на этой фазе **не воспроизвёлся**. Инструкция `no tables`
соблюдена во всех пяти запросах.

Дефект транспорта P5 (один вызов = 5+ запросов провайдеру) не исправлялся;
зафиксирован в `manifest.json` как `known_transport_defect`.
