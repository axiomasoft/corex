# P6 receipts

## P6.1 — Карта внутренних зон

- Создан нормативный документ `docs/architecture/core-zones.md` версии 1.0.0.
- Документ содержит восемь разделов: матрица, карта принадлежности, правила и исключения,
  обоснование Support/Data, цели P15, кандидаты уровней, не-обещания и enforcement.
- `find packages/core/src -type f -name '*.php' | wc -l` — **146**.
- Контрольная сумма карты — **146**: 141 файл в десяти зонах, 3 Composition, 2 Testing.
- Исходный baseline P6.1 в 109 файлов устарел после P4/P5: классифицированы все 37 новых
  файлов; строки не подгонялись и файлы не исключались.
- Публичные FQCN и PHP-код не изменялись. D32/D33 сохранены; `class_alias` не добавлялся.
- Раздел «не обещает» называет D32 и D33 явно: смена FQCN и вынос Testing из production-autoload в P6 не обещаны.
- `git diff --stat -- 'packages/'` — пусто для P6.1.
- `composer lint:check` — GREEN (`vendor/bin/pint --test`, exit 0).
- Обязательные маркеры `E-BASEMODEL`, `TenancyContracts`, `LITMUS_ELOQUENT`,
  `class_alias`, R-Z0/R-Z1/R-Z2/R-L3/R-T1 и `RT1_EXISTING` присутствуют.
- `git diff --check` для трёх declared files — GREEN.
- Повторная сверка 2026-09-22: каталоги §2.1 дают 89 файлов, поимённые строки §2.2 — 57,
  сумма 146 и совпадает с `find packages/core/src -name '*.php' | wc -l`.
  Число 109 в Validation — baseline дизайна; Implementation Rules требуют живой подсчёт.
- `composer lint:check` этой сессии — exit 0.
- `git diff --stat -- 'packages/'` показывает 37 ранее изменённых файлов чужого контура.
  P6.1 не меняет `packages/`.
