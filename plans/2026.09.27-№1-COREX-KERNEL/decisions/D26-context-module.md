---
id: D26
date: 2026-09-28
status: accepted
item: P00.1
items: [P00.1, P02, P07]
supersedes: []
superseded_by: null
---
# D26 — context-module (accepted)

**Actor:** author-choice (claude-opus-5-5/high, Claude Code, 2026-09-28) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6); frontier-вердикт P00.1 в пределах `research/P00-design.md` §2.
**Evidence:** RAG:— `artifacts/P00-design/facts/{import-matrix.txt,zone-consumers.tsv}`, `packages/*/composer.json`, `artifacts/P00/*.tsv`; `check-maps.py` (default/`--dependencies`) exit 0 на `b624936`+P00.1.

## Solution

Tenancy-зона ядра (`core/src/Tenancy`, 18 файлов) становится модулем ядра `CoreX\Context`: `Context\Data\{AccountRef,WorkspaceRef,TenantContext}`, `Context\Contracts\*` (включая SPI lifecycle `TenancyManager`, `TenantDatabase*`, `AccountConnectionResolver`, `IdentityPasswordRehasher`), `Context\Enums\ImpersonationRestrictedAction`, `Context\Exceptions\{CentralContextException,ImpersonationRestrictedException,ScopeContextMismatch}`. Остальные типы зоны (`Null*`, `SingleAccountResolver`, `GuardedTenantContextRestorer`, `ImpersonationGuard/State`, `ProvisionParams`, `DatabaseConnectionConfig`) — `CoreX\Context\` (публичны).

## Why

- F00 A5.2: `CoreX\Tenancy` принадлежит двум пакетам — FQCN не определяет владельца.
- «Контекст исполнения» (account, workspace, актор, impersonation) — смысл зоны; `Accounts` уже, `Tenancy` занят пакетом.
- Отклонение от досье F2 («null-реализации — Internal»): `NullImpersonationService`/`NullTenancyManager` использует tenancy, `SingleAccountResolver` — access, `GuardedTenantContextRestorer` — тесты tenancy/operations; `Internal` дал бы `internal-edge` (`check-maps.py --dependencies` с тестами). Классификация `#[Api]/#[Spi]` — P04.

## Consequences

P02 перенос; `CoreX\Tenancy` принадлежит только `corex-tenancy`; P07 реализует SPI за адаптером движка (D13).
