# Roadmap исполнения — 2026.09.12-№1-COREX-SUCCESSOR

**Обновлён:** 2026-09-12. Источник маршрутов и состава групп — `plan.md` §3; roadmap не переопределяет спецификации, зависимости или authority.

## Карта исполнения

| Item | Batch | Запуск | Model class/Effort | Readiness | Результат |
|:--|:--|:--|:--|:--|:--|
| P1.1 | B1 | plan-exec | implementation/high | Ready после design qualification | D3 package-lint policy и расширенный test perimeter |
| P1.2 | B1 | plan-exec | implementation/high | Ready независимо от D3 | D4 tenancy L12/L13 matrix evidence |
| P2.1 | solo | plan-exec | implementation/high | После GREEN P1 и настоящих D3/D4 | D5 non-vacuous department record parity |
| P3.1 | B3 | plan-run | frontier/high | После GREEN P2 и настоящего D5 | D6 нормативный owner proposal |
| P3.2 | B3 | plan-run | frontier/high | После GREEN P2 и настоящего D5 | D7 architecture owner proposal |
| P3.3 | B3 | plan-run | frontier/high | После GREEN P2 и настоящих D3/D4/D5 | D8 distribution owner proposal |

B1/B3 — recommended groups: ready subset и dependency-safe порядок допустимы; каждый item сохраняет собственные проверки и закрытие. Группы не пересекают phase closure. P2.1 отдельно из-за phase-local auth security seam и независимого bounded review тестовых fixtures. Review задаёт глубину проверки item, не разрешение phase audit.

## Стартовый batch

Рекомендация отдельного запуска B1 и его единственный актуальный native launch block принадлежат `handoff.md`. Эта design invocation не выполняет successor. Frozen scope ограничивает тесты/CI; новые несовместимости сначала воспроизводятся и сравниваются с baseline по Execution Rules.

## Отложенные bundles

Материализовать P2.1 bundle после появления D3/D4. P3 bundles — после D5 и остальных объявленных inputs. Отсутствие ещё не произведённого input — dependency-not-ready, а не ошибка producer и не разрешение подставить fictitious evidence. Downstream/lint проверяют readiness после каждого producer.

## Гейты владельца

| Где | Необходимое решение | Что остаётся запрещено |
|:--|:--|:--|
| D10 | Запрос finish разрешает три обязательных carriers | Реализация в текущем design root |
| P3.1 | Effective/target policy, L0 stack и foreign schema/source owners | Правка действующих норм без отдельного решения |
| P3.2 | Template hash, boxed init, domain и role writer ownership | Schema/public API/runtime delta без accepted owner D# и новой спецификации |
| P3.3 | Split allowlist/channel, private auth/source pins, live proof authority | Публикация, credentials, push/tag и новые compatibility promises |

Acceptance P3 — decision-ready пакеты и конкретные owner requests. Их GREEN не закрывает соответствующие runtime/release debts. Отсутствие будущего owner ответа остаётся видимым в queue/views.
