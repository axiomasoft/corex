# Зависимости экосистемы

CoreX использует публичные контракты Packman, Tenant, AzGuard и Vaulter.
Версия снимка Packman в корневом Composer-репозитории — `0.1.2`. Для `packman-modules` CoreX требует
`^0.1.1`, чтобы использовать публичный контракт компиляции. Для
Tenant (`^1.0@dev`), AzGuard (`^2.0@dev`) и Vaulter (`^1.0@dev`) проверяются
development-снимки владельцев. Ограничения версий сами по себе не проверяют
доступность удалённых исходников.

## Зафиксированные исходники

Корневой `composer.json` содержит inline-репозитории `type: package`; `composer.lock`
фиксирует разрешённые зависимости. Каждый снимок содержит точный Git commit и
PSR-4 пути внутри монорепозитория владельца. Они нужны потому, что одного VCS URL
монорепозитория недостаточно для обнаружения его дочерних Composer-пакетов.

| Владелец | Пакеты | Source reference |
|---|---|---|
| Packman (`0.1.2`) | `packman`, `packman-modules`, `packman-schema`, `packman-panels`, `packman-plugins`, `packman-devtools`, `packman-testkit` | `d4eaf2ad01d22a822368765d154ae51ff70981f2` |
| AzGuard | `azguard` | `42bdb639d3ab4ea2571c3026113cac7727c88b18` |
| Tenant | `tenant`, `tenant-laravel` | `6325c62d5a9c0afe1f7bce769b32b7b24c726294` |
| Vaulter | `vaulter-core`, `vaulter-media` | `b230f6e27b53c92145fa81be2fdeb89ea5c8ae03` |

Все имена в таблице имеют префикс `axiomasoft/`. Packman зафиксирован на `0.1.2`,
а остальные снимки используют `dev-main` и числовой branch alias владельца; это
метаданные разрешения, а не доказательство, что commit находится в опубликованном
remote `main`. AzGuard source URL указывает на публичный репозиторий
[`axiomasoft/azguard`](https://github.com/axiomasoft/azguard).
Его имя содержит `private`, но для скачивания исходников авторизация не нужна.
Snapshot metadata требуются из-за дочерних Composer-пакетов внутри монорепозитория,
а не из-за ограничения доступа. Проверенный commit AzGuard доступен
в [PR #131](https://github.com/axiomasoft/azguard/pull/131) для защищённой ветки.

При работе с локальными монорепозиториями владельцев до публикации commits в
remote GitHub чистый CI не сможет получить их только по `composer.lock`.
Локальный успешный install из доступного checkout/cache не доказывает remote install.

Для авторизации приложению достаточно потребовать `axiomasoft/corex-access`: Composer
установит движок транзитивно по закреплённому снимку. Работайте через CoreX
`AccessChecker` для проверки разрешения и `ScopeResolver` для ограничения выборки.

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
git -C /absolute/path/vaulter cat-file -e b230f6e27b53c92145fa81be2fdeb89ea5c8ae03^{commit}
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
`composer require 'axiomasoft/corex:^2.0'`, и сохраните lockfile приложения.
Split-артефакты CoreX не включают чужой исходный код и не предоставляют внешний
Composer-канал автоматически. Проверки consumer fixtures добавляют снимки владельцев
после candidate-репозитория; для их Git sources действуют те же требования доступа.

При обновлении владельца согласованно обновляют inline metadata и lockfile,
проверяют API адаптера и его интеграционные тесты. Перед публикацией CoreX каждый
используемый source reference должен быть доступен целевой среде; локальный patch
нужно доставить отдельно или заменить доступным проверенным upstream commit.

## Общие проверки Packman

`packman-devtools` и `packman-testkit` устанавливаются только для разработки.
`packman.json` перечисляет 20 пакетов и `products/flex-crm`; `extra.packman` хранит
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
