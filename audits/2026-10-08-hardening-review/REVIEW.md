# Ревью improve/core-hardening-1

Дата: 2026-10-08. Ревьюер: Codex. База: `509d619199ea99c2d050deec4ae988d708b6b690`.
Рассмотренный tip: `8d9dc86c`; 54 коммита. Исходный отчёт:
[2026-10-08-core-audit.md](../2026-10-08-core-audit.md).

**Решение: принять с доработками, интегрировать squash-коммитом в main.**
Основание — текущее поручение владельца действовать как ревьюер и тимлид.
Упоминания прежних согласований в отчёте бота не использовались как самостоятельное
полномочие. Принимаемые исключения и ограничения перечислены здесь.

Это разрешение интеграции исходников, а не разрешение стабильного релиза.
`unused-public` остаётся красным; актуальность release receipts и автоматическая
проверка PHP BC требуют отдельной работы перед 1.0. Дополнительный аудит выполняется
после интеграции и будет записан отдельно.

## Решения по изменениям

| Пункт бота | Вердикт | Обоснование / окончательное изменение |
| --- | --- | --- |
| F-01 | Принять | Pint исключает архивные доказательства, сохраняя проверки живого кода. |
| F-02 | Принять, доработать | Регистрация материализатора вынесена без увеличения потолка провайдера. Исправлены нарушения именованных аргументов в composition/ownership wiring. |
| F-03 | Принять | Удалены no-op освобождения объектов cURL/GD; ошибка передачи и проверка результата сохранены. См. официальный [PHP migration guide](https://www.php.net/manual/en/migration85.deprecated.php). |
| F-04 | Принять | strict_types в конфиге search-intl. |
| F-05 | Принять | Сравнение идентичности контейнера больше не экспортирует его окружение при assertion failure. |
| F-06 | Принять | Все foundation-пакеты обнаруживаются из Composer, вместо неполного списка из восьми. |
| F-07 | Принять | EventFake разворачивается до конкретного dispatcher: cleanup действительно удаляет свои listeners; PG regression проходит. |
| F-08 | Принять частично | Отчёт вынесен в tmp, cleanup в finally. Пробы всё ещё меняют src: параллельная безопасность не доказана и не заявляется. |
| F-09 | Принять | Синтаксически повреждённый installed.json теперь вызывает отказ lifecycle. Проверка структуры manifest остаётся открытой. |
| F-10 | Принять | Стиль тестов приведён к действующему Rector. |
| F-11 | Принять | Прямые тестовые зависимости объявлены владельцами пакетов, без runtime-зависимостей на PHPUnit/Mockery. |
| F-12 | Принять | Golden обновлён по рассмотренным изменениям. Снимок Internal не объявляется публичным BC-обещанием. |
| F-13 | Принять | Все прежние curl errno-ветви давали один код; удалена дублирующая классификация. |
| F-14 | Принять | Одна DNS admission policy внутри webhooks; запреты HTTPS/IP/host не расширены. |
| F-15 | Принять | Присутствующий неверный extra.corex.module вызывает ошибку, отсутствие остаётся законным для немодульного пакета. |
| F-16 | Принять, обновить | AST inventory отражает переносы; повторно выпущен после доработки EffectiveWorkspaceScope, без новых mutation exemptions. |
| F-17 | Доработать | Редакция ключа сохранена. Добавлена provenance по 17 изменённым и двум удалённым логам; старые receipt hashes не переписаны. Secret gate теперь охватывает untracked-файлы и не отключает generic-key rule для всех tests/audits. |
| F-18 | Принять | Scheduling-порты в ядре устраняют tenancy→wakeup; прежние имена и контейнерный alias сохранены. |
| F-19 | Доработать | Override и provider должны вернуть реализацию Eloquent Scope, проверяемую на boot даже при пустом entity registry. Два новых regression-сценария. |
| F-20 | Принять | DoD читает JSON и frozen owner index; схема/отказ preflight проверены 32 тестами. |
| F-21 | Доработать способом интеграции | Squash содержит снимок и REBASELINE в одном коммите. История исходной ветки не переписывается; anti-weakening не ослабляется. |
| F-22 | Принять | Kernel Invocation позволяет продукту поставлять обработчики без runtime operations. Tools/webhooks признаны расширениями operations. Закрытый список aliases принят текущим ревью, зафиксирован в ADR. |
| F-23 | Доработать | Убран stale live seal из общего CI, но тесты отказа release preflight и схема DoD возвращены в distribution-proof workflow. Публикация по красным receipts по-прежнему запрещена самим preflight. |
| F-24 | Принять | Машинные абсолютные symlinks, approval env и логи агентов исключены из Git. |
| F-25 | Принять, уточнить | Один Principal(type,id). Access alias совместим; прежний operations Principal с id() несовместим и требует миграции хоста на principalId()/principalType(). Исправлены ошибочные docblocks об alias типа на самого себя. |
| F-26 | Принять временно | Удаление старой plan-bound Roave-машинерии допустимо при подготовке ещё не опубликованного 1.0. Независимый BC-дифф обязателен **до** стабильного релиза. Freshness snapshot его не заменяет; semver-policy уточнена. |
| F-27 | Доработать | Команда composer dod-check возвращена с release/1.0 вместо plans/. Stale receipts не перебазированы ради зелёного результата. |
| F-28 | Принять | Тип субъекта входит в idempotency collision domain; regression исключает replay между user/agent с одинаковым id. Меняются старые scope hashes — rollout требует пустого либо явно мигрированного store. |
| F-29 | Принять | Frozen evidence отделён от живого плана, пути distribution scripts переведены в release/. Копии не превращаются в новый proof текущего релиза. |

## Решения по предложениям

| Предложение | Решение |
| --- | --- |
| P-01 | Редакция и усиленный gate приняты. Force-push/переписывание истории не выполняются: это отдельная разрушительная операция с координацией клонов. Заявление бота о неактивности ключа независимо не проверено. |
| P-02 | Принят реализованный гибрид; новая platform-ступень не нужна. |
| P-03 | Перенос Scheduling принят. Operations→transfer требует отдельного design/compatibility review, автоматический перенос DTO в ядро отвергнут. |
| P-04 | Принят и усилен boot validation scope. Boxed без tenancy остаётся законным профилем. |
| P-05 | Принять как следующий шаг: общий строгий InstalledManifest reader и tests неверной структуры, сохранив missing-file seam Testbench. |
| P-06 | Отложить: широкий CoreX PSR-4 сам по себе не доказал дефект; ownership gate уже защищает коллизии. |
| P-07 | Отложить: внутрипакетный вызов composition→Internal разрешён выбранной зонной моделью. Не вводить публичный SPI ради устранения двух импортов. |
| P-08 | Принять: изолировать отрицательные пробы вне живого дерева, проверить конкурентный запуск. |
| P-09 | Принять исправление compatibility passport. Удаление Illuminate dependencies без standalone proof не принято. |
| P-10 | Не принимать пересмотр Departments в L0 без нового предметного сценария; D29 не отменяется. |
| P-11 | Принять направленный поиск неполных inventories. Массовая замена всех явных списков discovery не обоснована: часть списков — политика, не inventory. |
| P-12 | Машинерия остаётся, plan-independent CLI и CI refusal tests восстановлены. Перевыпуск старых receipts не выдан за проверку нового candidate. |

## Дополнительные исправления перед интеграцией

Восемь multiargument calls приведены к adopted named-argument rule
(`php:named-arguments`): пять нарушений добавила ветка, три уже были в
SqlTaskRecurrenceMaterializer. `release-quality --stage=all` теперь зелёный.

Глобальный SHA-256 allowlist в исходной .gitleaks.toml приводил к пропуску
каталога audits в dir mode, несмотря на condition=AND. Он заменён rule-level
allowlist для generic-api-key; единичный исторический checksum sentry.png
допущен точным fingerprint. Семь отрицательных/положительных тестов проверяют
реальные границы scanner, включая generic и provider key внутри audits.
[Документация gitleaks 8.28](https://github.com/gitleaks/gitleaks/blob/v8.28.0/README.md)
подтверждает, что git mode читает patches, а отдельный dir mode нужен для новых файлов.

Прежние archived receipt hashes теперь расходятся с 17 redacted logs.
Это не скрыто: [archive-redactions.json](evidence/archive-redactions.json)
содержит прежние/новые hashes и действия, не значения ключа.

## Проверки ревьюера

Среда: PHP 8.4.26; PostgreSQL 16 в corex_pgsql_test, localhost:5434.
Отдельная БД corex_hardening_review_test; SQLite — :memory:.
Live CI, внешние hosted adapters, mutation score и release publication не проверялись.

| Проверка | Результат / evidence |
| --- | --- |
| Изменённые SQLite-контуры root/operations/tools/webhooks/modules/tasks-workflow | 900 passed, [scoped-sqlite.json](evidence/scoped-sqlite.json) |
| Core lifecycle/materialization PG | 114 passed, [scoped-pg.json](evidence/scoped-pg.json) |
| Access standalone SQLite / PG | 78 / 111 passed, [SQLite](evidence/access-sqlite.json), [PG](evidence/access-pg.log) |
| Tasks-workflow после именованных аргументов | 33 passed, [materialization-final.json](evidence/materialization-final.json) |
| Архитектура | 145 passed, [arch.json](evidence/arch.json) |
| Golden | 7 passed, [golden.json](evidence/golden.json) |
| PHPStan всех 23 конфигураций / tests-lint | 0 errors, [analyse.log](evidence/analyse.log), [analyse-tests.log](evidence/analyse-tests.log) |
| Pint | passed, [lint-final.json](evidence/lint-final.json) |
| Release-quality | quality all OK, [release-quality.log](evidence/release-quality.log) |
| PHP API freshness | diagnostics=[], [php-api-final.log](evidence/php-api-final.log) |
| DoD schema / release refusal tests | 32 passed, [release-tooling-tests.log](evidence/release-tooling-tests.log) |
| Secret scan / границы | clean / 7 passed, [scan](evidence/secret-scan-final.log), [tests](evidence/secret-scan-tests.log) |
| Mutation inventory | 1484 files / 25 fragments / 394 exact declarations, [inventory](evidence/mutation-inventory.log) |
| Package graph / module layout / config composition | passed, evidence в этом каталоге |
| Composer manifests / package docs | valid / passed; [validate](evidence/composer-validate.log), [docs](evidence/package-docs.log) |
| Dependency analyser | Все остальные пакеты чистые; core чистый на 436 tracked файлах. Исходный core scan захватил 7773 файла вместе с ignored consumer/vendor: [чистый срез](evidence/deps-core-clean.log), [локальный прогон](evidence/deps.log). |
| Rector изменённого PHP | Финальный результат: [refactor-changed.json](evidence/refactor-changed.json). Общий запуск остановлен, поскольку захватывает те же ignored consumer dependencies; его зелёный результат не заявляется. |
| Unused-public | 179 stale + 177 unused findings; 357 строк с завершающим статусом. Старый debt не скрыт: [unused-public-final.log](evidence/unused-public-final.log). |

Проверки с отрицательными Arch-пробами выполняются последовательно с source scanners:
параллельный запуск действительно показал временный DigestTick lint failure и
RuntimeGlobalsProbe в API report. После удаления проб Pint/API повторно зелёные.
Это доказательство P-08, а не дефект штатного DigestTick/API.

Финальные проверки squash diff и состояния main записываются в
[integration.json](evidence/integration.json). Исходные ссылки бота на зелёные
прогоны более ранних tips не принимались за проверку нынешнего tip.
