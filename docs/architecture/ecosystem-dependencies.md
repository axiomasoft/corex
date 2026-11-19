# Зависимости экосистемы

CoreX использует публичные контракты Packman, Tenant, AzGuard и Vaulter.
Packman зафиксирован на версии `0.1.1`. Для `packman-modules` CoreX требует
`^0.1.1`, чтобы использовать публичный контракт компиляции. Для
Tenant (`^1.0@dev`), AzGuard (`^2.0@dev`) и Vaulter (`^1.0@dev`) проверяются
development-снимки владельцев. Ограничения в Composer не утверждают, что эти
источники доступны без авторизации.

## Зафиксированные исходники

Корневой `composer.json` содержит inline-репозитории `type: package`; `composer.lock`
фиксирует разрешённые зависимости. Каждый снимок содержит точный Git commit и
PSR-4 пути внутри монорепозитория владельца. Они нужны потому, что одного VCS URL
монорепозитория недостаточно для обнаружения его дочерних Composer-пакетов.

| Владелец | Пакеты | Source reference |
|---|---|---|
| Packman (`0.1.1`) | `packman`, `packman-modules`, `packman-schema`, `packman-panels`, `packman-plugins`, `packman-devtools`, `packman-testkit` | `b2fea80d85b1787f0a74d22c0f2563fd411ad132` |
| AzGuard | `azguard` | `1718b337ac237a260c2876ecd5fafa5867c9a9b3` |
| Tenant | `tenant`, `tenant-laravel` | `8d1b62c245f5547d870f124a995bb20af3ab51be` |
| Vaulter | `vaulter-core`, `vaulter-media` | `f28c91f366e791f5db20a1a914260c42b413a97c` |

Все имена в таблице имеют префикс `axiomasoft/`. Packman зафиксирован на `0.1.1`,
а остальные снимки используют `dev-main` и числовой branch alias владельца; это
метаданные разрешения, а не доказательство, что commit находится в опубликованном
remote `main`. AzGuard source URL указывает на `axiomasoft/azguard-private`; среде
установки нужен доступ к этому репозиторию.

При работе с локальными монорепозиториями владельцев до публикации commits в
remote GitHub чистый CI не сможет получить их только по `composer.lock`.
Локальный успешный install из доступного checkout/cache не доказывает remote install.

## Разработка в монорепозитории

При доступности всех source commits запускайте из корня:

```bash
scripts/php84 --check
scripts/php84 composer install --prefer-source
```

CoreX-пакеты и продукт устанавливаются из относительных `packages/*` и `products/*`.
Внешние снимки не зависят от соседних директорий. Для локального checkout Vaulter
нужен переданный checkout с указанным commit или Git bundle, развёрнутый в отдельный
локальный репозиторий. Проверить commit можно без изменения checkout:

```bash
git -C /absolute/path/vaulter cat-file -e f28c91f366e791f5db20a1a914260c42b413a97c^{commit}
```

Для локальной установки можно направить только Vaulter source URL на этот checkout
через настройки Git **одного процесса**, сохранив Composer-манифест и lockfile:

```bash
env \
  GIT_CONFIG_COUNT=1 \
  GIT_CONFIG_KEY_0=url.file:///absolute/path/vaulter/.insteadOf \
  GIT_CONFIG_VALUE_0=https://github.com/axiomasoft/vaulter.git \
  scripts/php84 composer install --prefer-source
```

Замените абсолютный путь своим. Если Git environment уже настроен через
`GIT_CONFIG_COUNT`, добавьте правило к нему, сохранив прежние записи. Не коммитьте
машинные пути, токены или credentials в Composer metadata. Публикация patch в
upstream либо доставка проверенного Git bundle в CI — отдельный обязательный шаг
перед воспроизводимой установкой на чистой машине.

## Отдельная установка пакета для разработки

Корневой `composer install` достаточен для обычных проверок монорепозитория.
Если проверке нужен собственный `vendor` пакета, используйте общий wrapper:

```bash
scripts/php84 python3 scripts/composer-with-snapshots.py --working-dir=packages/media update --prefer-source --no-interaction
scripts/php84 python3 scripts/composer-with-snapshots.py --working-dir=packages/media audit --locked
```

Wrapper использует то же наследование репозиториев, что и source audit: сохраняет
пакетные объявления, затем добавляет репозитории родителей. Локальные path-зависимости
должны находиться внутри этого checkout. Внешние development-зависимости разрешены
только при точном совпадении source и package metadata с reviewed root lock; общий
`minimum-stability` остаётся `stable`. Composer требует root stability flags, поэтому
wrapper добавляет `@dev` только достижимым по `require` проверенным снимкам владельцев;
пакеты из остальных optional-интеграций не устанавливаются.
При `update` PHPStan, strict rules и Larastan получают точные версии из root lock,
если входят в граф зависимостей пакета; тот же список инструментов использует root CI.
Packman devtools/testkit также входят в reviewed toolchain; wrapper добавляет их
точные версии только когда они достижимы, без установки лишних dev-зависимостей.

Рядом с исходным манифестом создаются игнорируемые `.composer-snapshots.json` и
`.composer-snapshots.lock`. Исходные `composer.json`/`composer.lock` не переписываются;
`vendor` и пути autoload остаются локальными для пакета. Последующие `install`,
`validate` и `audit --locked` этого окружения запускаются тем же wrapper. Обычные
Composer scripts пакета используют установленный `vendor`. Генерируемые manifests
и locks содержат локальные paths, поэтому не входят в артефакты публикации.
Octane consumer harness использует эту же установку; его исходный манифест сохраняет
точный PHPUnit constraint, а устаревший lock до выделения Packman удалён.

## Приложение-потребитель и дистрибуция

Composer не наследует `repositories` зависимостей. Корень приложения должен иметь
свой одобренный CoreX Composer-канал и соответствующие внешние package-снимки либо
доступный Composer-канал владельцев с эквивалентными пакетами. Не копируйте
монорепозиторные `path` repositories в приложение. Для временного snapshot-канала
копируются целиком соответствующие `type: package` записи, включая `source`,
`require`, `autoload`, providers и branch aliases; один commit без этих метаданных
не описывает дочерний пакет.

После настройки канала выберите нужные пакеты, например
`composer require 'axiomasoft/corex:^1.0@dev'`, и сохраните lockfile приложения.
Split-артефакты CoreX не включают чужой исходный код и не предоставляют внешний
Composer-канал автоматически. Проверки consumer fixtures добавляют снимки владельцев
после candidate-репозитория; для их Git sources действуют те же требования доступа.

При обновлении владельца согласованно обновляют inline metadata и lockfile,
проверяют API адаптера и его интеграционные тесты. Перед публикацией CoreX каждый
используемый source reference должен быть доступен целевой среде; локальный patch
нужно доставить отдельно или заменить доступным проверенным upstream commit.

## Общие проверки Packman

`packman-devtools` и `packman-testkit` устанавливаются только для разработки.
`packman.json` перечисляет 21 пакет и `products/flex-crm`; `extra.packman` хранит
таксономию и error-prefix. `extra.corex` остаётся входом предметных гейтов CoreX.
Модули с lifecycle сохраняют `type: packman-module` и `extra.packman.module`.
Локальные профили в `schemas/profiles/` уточняют Composer type и лимит 80 строк
для модулей и продукта; они не отключают проверки поверхности.

```bash
scripts/php84 vendor/bin/packman gate
scripts/php84 vendor/bin/packman php-api --check
scripts/php84 vendor/bin/packman error-codes --check
scripts/php84 vendor/bin/packman config-schema --check
```

Все проверки запускаются последовательно. Архитектурные negative tests временно
меняют исходники: во время `composer test` нельзя запускать Rector, PHPStan,
Packman или другой source/API/graph scanner.

Packman пишет `resources/contracts/php-api.json`. CoreX `composer php-api` дополнительно
проверяет полную типовую границу и справочник публичного API. Error URN использует
`urn:packman:problem:`; доменные коды CoreX сохраняют собственные префиксы.

Нативный `TableWriteOwnershipGate` в Packman сверяет `gates.table_owners[table]`
с текущим Composer-пакетом: запись в собственные `sys_*` разрешена только явному
владельцу из каталога, а неизвестные системные таблицы и чужие владельцы запрещены;
DATA-003 независимо проверяет реальные записи.
Все четыре source reference из таблицы должны быть доступны CI через canonical
GitHub URL либо переданный checkout/bundle. Для каждого локального источника
добавляйте отдельный `url.file:///.../.insteadOf` в process-local `GIT_CONFIG_COUNT`;
не меняйте sibling checkout, глобальную конфигурацию Git или Composer source URLs.
