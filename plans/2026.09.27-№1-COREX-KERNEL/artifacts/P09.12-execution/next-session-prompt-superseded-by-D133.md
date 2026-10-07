# Промпт для новой сессии

Скопируй текст ниже в новую сессию на выбранной дешёвой модели с effort не ниже medium.

---

$ task:plan-run 2026.09.27-№1-COREX-KERNEL P09.12

Продолжи только P09.12 в `/home/vostrikov/projects/packages/corex`. Владелец поручил предыдущей сильной модели закончить код и передать дешёвой модели долгие мутационные прогоны и исправления тестов. Это уже записано в item: route `economy/medium`. Не начинай P09.13 и не публикуй изменения. Работай самостоятельно до принятого результата либо честного исчерпания бюджета.

Сначала открой `plans/ACTIVE.md`, канонический JSON `phases/P09/P09.12.json`, этот промпт, `artifacts/P09.12-execution/work-state.json` и `latest-diagnostics.json`. Пути последних файлов относительно `plans/2026.09.27-№1-COREX-KERNEL/`. Читай только нужные разделы требуемых source JSON, не огромный полный context и не generated views.

Task runtime:

```bash
python3 /home/vostrikov/projects/packages/swissknifeman/packages/task/scripts/plan.py next 2026.09.27-№1-COREX-KERNEL
```

Актуальная каноническая команда находится в `/home/vostrikov/projects/packages/swissknifeman/packages/task/commands/plan-run.md`. Локальный адаптер может ссылаться на удалённый `plan-work.py`; используй актуальный `plan.py`. Возобнови item через `start` с **наблюдаемыми** provider/model/effort/session этой новой сессии. Не подставляй executor предыдущей сессии. Меняй план только через `plan.py`.

## Состояние кода

Владелец остановил дальнейшие проверки в предыдущей сессии и потребовал немедленно передать код. Checkpoint-коммит ещё НЕ создан: все изменения кода и artifacts сохранены в worktree. Item намеренно остаётся `in_progress`, завершение/95% не заявлено. Исходники `packages/*/src` не менялись. Сначала сохрани scoped checkpoint-коммит собственных изменений P09.12 через plan.py commit с --leave-outside; проверку hooks и окончательные прогоны выполняй в этой новой сессии. Ожидаемый subject: test(quality): repair mutation execution and strengthen security contract tests (PLAN3.P09.12).

Исправлены доказанные дефекты инструмента: загрузка package Pest.php и vendor-autoload в дочерних фикстурах; Testbench storage и shipped .env stub; пустой Infection excludes; отображение Pest/PHPUnit классов через настоящий JUnit; ранний перехват мутанта до Pest.php без преждевременной загрузки Composer; фильтры по всем покрывающим **классам**, чтобы не получать `No tests found` от PCRE-паттерна на 53 КБ; отдельная копия истории начального прогона; независимый guardian для сохранения реального exit при Symfony `stop(0)` и завершения дочернего процесса.

`@default` сохранён. Дополнительно включены `NullReturn`, `NonNullReturn`, `DefaultObjectArgument` для поведенческих файлов, на которых стандартный профиль давал ноль. AST ledger содержит 393 declarations из 1476 production-файлов; исполняемые defaults/ветвления остаются behavior. Полный source map не урезан. Equivalent ledger пуст, исключения не добавлены. Новая классификация declaration ledger ещё подлежит независимой проверке P09.8.

Добавлены проверки Core (контекст, authz, JSON, схемы), Operations (recursive field authorization/default deny), Tools (настоящий CLI wire contract/default metadata), Access (options/errors), Access-AzGuard (binding/grants/Blade), Webhooks (snapshot, границы входов/CIDR, key rotation, retries). Последняя правка Tenancy восстанавливает конкретную template-базу `_test`, оставшуюся после прерванного мутанта; `PendingPoolTest` теперь проходит 13 тестов.

Логи проверок: `runner-checkpoint.log` — 40 passed, включая настоящий timeout Symfony и eagerly-loaded mutant; `lint-checkpoint.log` и `analyse-checkpoint.log` — вывод последних проверок, их exit/source freshness пересверь; предыдущие полные проверки были зелёными; `inventory-checkpoint.log` — 1476/25/393; `fixed-mutations.log` — 35/35 killed/restored. Весь начальный native Tenancy suite раньше проходил 631 тест без пропусков. Это не итоговый MSI и не свежий полный прогон после последних изменений. Финальные boundary tests Core: `last-contract-tests.log`, 18 passed / 70 assertions.

## Что пока не доказано

Нет ни одного действительного текущего GREEN acceptance receipt. **Все старые receipts глобально устарели**: hashes включают тесты и tooling всего репозитория. Не предъявляй прежние числа как текущий результат.

Старые диагностические MSI: Operations 98.84%, Access-AzGuard 95.81%, Webhooks 58.02% до новых boundary tests. Последний полный Core report — 86.72% (497 mutants, 431 kills, 14 escaped, 1 uncovered, 51 timeouts), но runner правильно отклонил его из-за oversized-filter `No tests found`. Access — 78.07%, тоже отклонён из-за отсутствовавших raw exits после hard timeout; guardian теперь исправлен. Tools последний ранний прогон остановлен при передаче, полного нового отчёта нет. Tenancy новый baseline упал на оставшейся template-фикстуре; её восстановление исправлено и проверено. Ранее Tenancy достигал 60-минутного лимита worker без полного report.

Явные raw directories в `latest-diagnostics.json`; не выбирай «последний» по mtime, поскольку очистка старых sandbox изменила timestamps. Крупные raw артефакты лежат в `build/quality/mutation/<package>-security/<uuid>/`. `pgsql/mutants.json` содержит stats и группы escaped/uncovered/timeouted, реальные source/diff; `processes/*.json`, stdout/stderr и coverage/JUnit сохраняют доказательства. Читай небольшую сводку и целевые mutants, не печатай все исходники JSON.

## Среда и запуск

Применяй `devops:db-test-preflight` и test-isolation guard перед PG. PostgreSQL `corex_pgsql_test` healthy на 127.0.0.1:5434. Runner создаёт собственные `corex_msi_<package>_security_test`, имена должны оканчиваться `_test`. Не запускай два worker одного package/zone одновременно: DB одна на fragment. Не запускай обычные PG-тесты в DB одновременно с её worker.

Собственные Redis `corex_p0912_redis` (32768) и S3 `corex_p0912_s3` (32770) специально оставлены работающими для продолжения. Failed stopped container `corex_p0912_minio` не используется. Shared PostgreSQL не останавливай. Тестовые креды S3 в committed `native-storage-config.json` искусственные, bucket `corex-msi-security-test`. Для данного checkout:

```bash
docker compose ps
docker start corex_p0912_redis corex_p0912_s3
mkdir -p build/quality/security-services
cp 'plans/2026.09.27-№1-COREX-KERNEL/artifacts/P09.12-execution/native-storage-config.json' build/quality/security-services/storage.json
export PGSQL_PORT=5434
export P3_REDIS_PORT=32768
export COREX_P3_STORAGE_CONFIG=/home/vostrikov/projects/packages/corex/build/quality/security-services/storage.json
scripts/php84 php -r '$c=json_decode(file_get_contents(getenv("COREX_P3_STORAGE_CONFIG")),true,512,JSON_THROW_ON_ERROR); $s=new \Aws\S3\S3Client(["version"=>"latest","region"=>$c["region"],"endpoint"=>$c["endpoint"],"use_path_style_endpoint"=>true,"credentials"=>["key"=>$c["key"],"secret"=>$c["secret"]]]); if(!$s->doesBucketExistV2($c["bucket"])){$s->createBucket(["Bucket"=>$c["bucket"]]);} echo "native S3 ready\n";'
```

Проверь фактические ports/health и наличие bucket (Minio tmpfs теряет bucket после остановки). Если named контейнеры отсутствуют, пересоздай собственные loopback-only fixtures из image IDs в `services.json`, сохрани новую среду и её hashes. Не отключай native tests и не меняй SDK/engine pins для обхода ошибок.

Все следующие mutation commands выполняй с **одинаковой** native средой, включая пакеты без S3, иначе manifests несовместимы:

```bash
scripts/php84 python3 scripts/quality/mutation.py --inventory --check
scripts/php84 python3 scripts/quality/mutation.py --self-test
scripts/php84 python3 scripts/quality/mutation.py --package core --zone security
```

Затем так же запускай `access`, `tools`, `webhooks`, `tenancy`, `operations`, `access-azguard`. Не более четырёх worker одновременно; `--threads=1` и 60-минутный budget каждого worker не меняй. Сейчас свободно около 14GB: четыре snapshot требуют несколько GB. Удаляй только собственные superseded sandbox после остановки процессов, сохраняя raw reports/coverage/process evidence. Чужие планы/build не трогай.

## Оптимальный порядок

1. По последнему прямому указанию владельца предыдущая сессия больше не запускает никаких проверок; все дальнейшие прогоны и мелкие исправления принадлежат этой новой сессии. Сначала новые диагностические замеры Core/Access/Tools и отдельно Tenancy — подтвердить, что исправленные class filter/guardian/fixture дают полный честный report. Webhooks обязательно измерить после новых тестов.
2. Для каждого красного fragment сгруппируй escaped/uncovered/timeouted по файлу. Добавляй meaningful regression для реального контрактного дефекта. Сначала быстрый целевой Pest test; затем новый замер affected fragment. Passing мутант, прерванный по timeout, остаётся **неубитым**. Ошибки запуска/пустые tests/нет raw exit — инфраструктурный RED, не kill.
3. Не меняй тесты, source, tooling, lock/config одновременно с финальным измерением: любое изменение инвалидирует **все семь** receipts. Разделяй фазу исправлений и фазу окончательного измерения. Старые исходники в sandbox можно использовать только для диагностики, не current acceptance.
4. Сохраняй security95 для **каждого** из 7 fragments, полные исходники и `@default`; никакого source narrowing, перевода behavior в declarations, снижения порога, retire mutators, увеличения equivalent allowance или молчаливого production/API/schema delta. Реальную semantic/tooling проблему исправляй по воспроизводимому примеру, не ради цифры; выход за принятую границу зафиксируй в текущем item.
5. Полный acceptance V1: `scripts/php84 python3 scripts/quality/mutation.py --zone security` с native env выше. Он выполняет fragments последовательно и останавливается на первом красном. `--all` проверяет **все 25** fragments, а не только security, и относится к общей фазе: не гоняй его бесцельно для P09.12. V2/V3/V4/V5 и процедура V6 находятся в canonical item JSON. Долгие логи складывай в artifact dir. Коротко сообщай прогресс примерно раз в минуту; не обещай срок по одному тесту.
6. Используй остаток исходного лимита **два раунда по четыре активных часа**, данные в `work-state.json` (на checkpoint уже использовано около 175 минут). Между сессиями бюджет не расходуется, смена модели/сессии не обнуляет использованное время. По исчерпании — честный `blocked` с raw evidence и точным остатком. Не маркируй item done/green, если 95% либо Validation не достигнуты.
7. При успешном завершении: `plan.py finish` с фактическими V1–V6, один scoped completion commit, точная строка `plan.py next`. При незавершении сохрани handoff и status. Чужой dirty P09.9/плановые projections не включай в commit. Новый независимый review автоматически не запускай; P09.8 владеет phase review.

Foreign dirty на входе: `artifacts/P09.9-stack-proposal/`, `knowledge/P09.9-stack-proposal.json` и projections D03/D120/D131/D132, P09.5/P09.9, research-P09-design, handoff/open-questions/plan/roadmap/status. Это работа владельца, не твой ремонт. Не выполняй `git add .` или restore/reset всего дерева.

В конце дай реальные MSI всех 7 пакетов, статус принятия, проверенные изменения и точный следующий шаг. В mAInd только один короткий source-linked `active-handoff`, без журналов тестов и текста плана.
