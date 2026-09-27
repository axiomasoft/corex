# Команды исполнения PLAN2: P7–P17

**Проверено:** 2026-09-23 по `agent --list-models` и
`execution-batches.md`. Это отдельные запуски, выполняемые по одному
после терминального checkpoint предыдущего item. Не вставлять весь список
в shell одним блоком. В группе `task:plan-run` каждый item получает свой
receipt/commit/closure; при недоступном predecessor или owner gate выполнять
только готовое подмножество согласно `plan.md` и заново сформированному handoff.

CLI `agent` — Cursor. Для Codex использовать projection `plan.md` §3.3.
Команды ниже не предоставляют owner gates: перед помеченным запуском
требуется уже принятый точный grant. P15.11/P15.12 — условные ветки;
P17.12 — реальная внешняя публикация только после G-RELEASE.

Текущий frontier: P7.1. После P17.12 выполнить архивирование плана
отдельной командой в конце.

## P7

```bash
# E07a
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P7.1'
# E07b
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P7.12'
# E07c
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P7.2'
# E07d
agent --workspace /home/vostrikov/projects/packages/corex --model cursor-grok-4.6-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P7.3-P7.5'
# E07e
agent --workspace /home/vostrikov/projects/packages/corex --model composer-2.5 --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P7.6-P7.7'
# E07f
agent --workspace /home/vostrikov/projects/packages/corex --model composer-2.5 --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P7.8-P7.9'
# E07g
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P7.10'
# E07h — fresh review
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P7.11'
```

## P8

```bash
# E08a
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P8.1'
# E08b
agent --workspace /home/vostrikov/projects/packages/corex --model cursor-grok-4.6-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P8.2-P8.3'
# E08c
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P8.4'
# E08d
agent --workspace /home/vostrikov/projects/packages/corex --model cursor-grok-4.6-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P8.5'
# E08e — fresh review
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P8.6'
```

## P9

```bash
# E09a
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P9.1'
# E09b
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P9.2'
# E09c
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P9.3'
# E09d
agent --workspace /home/vostrikov/projects/packages/corex --model cursor-grok-4.6-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P9.4-P9.6'
# E09e
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P9.7'
# E09f — fresh review
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-xhigh --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P9.8'
```

## P10

```bash
# E10a
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P10.1-P10.2'
# E10b — grant G-DDL требуется
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P10.3'
# E10c
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P10.4'
# E10d
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P10.5'
# E10e
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P10.6-P10.7'
# E10f — fresh review
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-xhigh --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P10.8'
```

## P11

```bash
# E11a
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P11.1'
# E11b
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P11.2-P11.3'
# E11c
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P11.4'
# E11d
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P11.5'
# E11e
agent --workspace /home/vostrikov/projects/packages/corex --model cursor-grok-4.6-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P11.6-P11.7'
# E11f — fresh review
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-xhigh --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P11.8'
```

## P12

```bash
# E12a
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P12.1'
# E12b
agent --workspace /home/vostrikov/projects/packages/corex --model cursor-grok-4.6-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P12.2-P12.3'
# E12c
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P12.4-P12.5'
# E12d
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P12.6-P12.7'
# E12e — fresh review
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P12.8'
```

## P13

```bash
# E13a
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P13.1-P13.2'
# E13b
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P13.3-P13.4'
# E13c — grant G-DDL требуется
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P13.5'
# E13d
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P13.6'
# E13e
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P13.7'
# E13f
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P13.8'
# E13g
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P13.9-P13.10'
# E13h
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P13.11'
# E13i — grant G-API требуется — fresh review
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-xhigh --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P13.12'
```

## P14

```bash
# E14a
agent --workspace /home/vostrikov/projects/packages/corex --model cursor-grok-4.6-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P14.1-P14.2'
# E14b — grant G-DDL требуется
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P14.3'
# E14c
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P14.4-P14.5'
# E14d
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P14.6'
# E14e
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P14.7-P14.8'
# E14f
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P14.9-P14.10'
# E14g
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P14.11-P14.12'
# E14h
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P14.13'
# E14i
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P14.14'
# E14j
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P14.15-P14.16'
# E14k
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P14.17'
# E14l
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P14.18'
# E14m — grant G-SECRET требуется
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P14.19'
# E14n
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P14.20'
# E14o — fresh review
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-xhigh --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P14.21'
```

## P15

```bash
# E15a — grant G-API-P15 требуется
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P15.1'
# E15b
agent --workspace /home/vostrikov/projects/packages/corex --model composer-2.5 --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P15.2-P15.6'
# E15c — grant G-API-P15 требуется
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P15.7-P15.8'
# E15d
agent --workspace /home/vostrikov/projects/packages/corex --model composer-2.5 --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P15.9'
# E15e — grant G-API-P15 требуется
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P15.10'
# E15f — grant G-PKG-API, G-PKG-AUTH требуется
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P15.11-P15.12'
# E15g
agent --workspace /home/vostrikov/projects/packages/corex --model cursor-grok-4.6-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P15.13'
# E15h — fresh review
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P15.14'
```

## P16

```bash
# E16a
agent --workspace /home/vostrikov/projects/packages/corex --model composer-2.5 --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P16.1-P16.3'
# E16b
agent --workspace /home/vostrikov/projects/packages/corex --model cursor-grok-4.6-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P16.4-P16.6'
# E16c
agent --workspace /home/vostrikov/projects/packages/corex --model composer-2.5 --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P16.7-P16.8'
# E16d
agent --workspace /home/vostrikov/projects/packages/corex --model composer-2.5 --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P16.9'
# E16e
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P16.10'
# E16f
agent --workspace /home/vostrikov/projects/packages/corex --model composer-2.5 --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P16.11'
# E16g
agent --workspace /home/vostrikov/projects/packages/corex --model cursor-grok-4.6-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P16.12'
# E16h — fresh review
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P16.13'
```

## P17

```bash
# E17a
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P17.1'
# E17b
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P17.2-P17.5'
# E17c
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P17.6-P17.7'
# E17d
agent --workspace /home/vostrikov/projects/packages/corex --model cursor-grok-4.6-high --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P17.8'
# E17e
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P17.9-P17.10'
# E17f — fresh review
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-max --force --sandbox disabled --trust --approve-mcps 'task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P17.11'
# E17g — grant G-RELEASE требуется
agent --workspace /home/vostrikov/projects/packages/corex --model gpt-5.6-sol-max --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P17.12'
```

## Закрытие плана

После терминального P17.12 и сверки release receipt:

```bash
agent --workspace /home/vostrikov/projects/packages/corex --model grok-4.7-high --force --sandbox disabled --trust --approve-mcps 'task:plan-close archive 2026.09.19-№1-COREX-PLATFORM-HARDENING'
```
