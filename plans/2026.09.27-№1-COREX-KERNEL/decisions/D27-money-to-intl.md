---
id: D27
date: 2026-09-28
status: accepted
item: P00.1
items: [P00.1, P02, P04, P05]
supersedes: []
superseded_by: null
---
# D27 — money-to-intl (accepted)

**Actor:** author-choice (claude-opus-5-5/high, Claude Code, 2026-09-28) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6); frontier-вердикт P00.1 в пределах `research/P00-design.md` §2.
**Evidence:** RAG:— `artifacts/P00-design/facts/{import-matrix.txt,zone-consumers.tsv}`, `packages/*/composer.json`, `artifacts/P00/*.tsv`; `check-maps.py` (default/`--dependencies`) exit 0 на `b624936`+P00.1.

## Решение

`CoreX\Money\MoneyCast` → `CoreX\Intl\Money\MoneyCast` (`packages/intl/src/Money/`); ядро теряет `brick/money`. Ребро `modules/Compiler/RegistryCompiler` → `MoneyCast` инвертируется: ядро экспортирует SPI `CoreX\Modules\Contracts\FieldCastRegistry` (#[Spi(Call)]) «cast FQCN → тип поля», intl регистрирует `MoneyCast → money`; компилятор реестра читает только реестр. Реализация шва — P02 до split (строка `dependency-edges.tsv`, invert intl→kernel); атрибуты — P04, typed options — P05.

## Почему

- 2 потребителя по одному импорту (crm, modules); `corex-intl` уже требует `brick/money` и владеет Currency/Money-форматированием.
- `RegistryCompiler.php:22,308` жёстко знает конкретный cast — нарушение принципа «ядро не знает пакетов» (`research/02` §1.1).
- Отклонено: отдельный `corex-money` — нет потребителя, которому нужен cast без intl.

## Consequences

P02: перенос файла, SPI, регистрация в `IntlServiceProvider`; `flex/crm` импортирует `CoreX\Intl\Money\MoneyCast`.
