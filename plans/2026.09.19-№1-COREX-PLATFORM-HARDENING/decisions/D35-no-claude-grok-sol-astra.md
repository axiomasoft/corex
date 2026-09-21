---
id: D35
date: 2026-09-19
status: superseded
item: P7
items: [P7, P8, P9, P10, P11, P12, P13, P14, P15, P16, P17]
supersedes: [D10]
superseded_by: D36
---
# D35 — Оставшиеся design-проходы и стадия 2 без Claude: Grok, затем Sol, Astra крайний случай

**Actor:** owner (Dmitry Vostrikov), 2026-09-19 · зафиксировано plan-designer/cursor-grok-4.6/high
**Evidence:** RAG:— владелец: модели Claude для этого плана подключить больше нельзя.
RAG:— идентификатор Astra в экосистеме — `gpt-6-astra` (swissknifeman runtime-selection).
RAG:— D10 уже держал Grok 4.6 как дефолт design-прохода; Claude Opus/Sonnet были
исключением для пяти плюс четырёх фаз. Пункты D10 про Composer 2.5 на items,
запрет Fast-тира и запрет смены класса вниз сохраняются.

## Решение

1. Живая таблица `plan.md` §3.3 больше не назначает Claude Opus 5 и Claude Sonnet 5.
   Исторические проходы P1, P4, P5 на Opus не переписываются.
2. Все оставшиеся design-проходы (P7–P17) и общий аудит стадии 2 идут на
   **Grok 4.6 (effort `high`)**. Это тот же frontier/high класс, что уже использован
   на P2, P3, P6; для бывших Sonnet-фаз это повышение класса, не понижение.
3. Запасной ряд, если назначенную модель нельзя запустить: **GPT-5.6 Sol**, затем
   **GPT-6 Astra** (`gpt-6-astra`) как крайний случай. Astra не выбирается, пока
   доступны Grok или Sol.
4. Исполнение items остаётся на Composer 2.5, стандартный тир. Gemini 3.1 Pro и
   Claude Fable 5.1 по-прежнему не используются.

Cursor-slug запуска: Grok — `cursor-grok-4.6-high`; Sol — `gpt-5.6-sol`;
Astra — `gpt-6-astra`.

## Почему

Владелец закрыл доступ к Claude. Альтернатива «оставить пины Opus/Sonnet и ждать
подключения» блокирует стадию 1. Альтернатива «сразу Astra» отклонена владельцем
как крайний случай: в экосистеме Astra чувствительна к конфликтным инструкциям.
Grok уже доказал design-проход этого плана (P2, P3, P6) без доплаты за длинный
контекст. Sol дороже на output и имеет надбавку свыше 272k; поэтому он запасной,
а не дефолт — снятие запрета D10 на Sol действует только как fallback.

## Consequences

`plan.md` §3.1a, §3.1b, §3.3, `roadmap.md` §1/§3 и `handoff.md` (Next = P7)
переназначают оставшиеся Claude-слоты. D10 остаётся историческим источником
ставок и аргумента про Composer на items.
