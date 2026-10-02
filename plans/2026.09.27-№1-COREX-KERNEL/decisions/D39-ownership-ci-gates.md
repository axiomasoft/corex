---
id: D39
date: 2026-09-28
status: accepted
item: P01.3
items: [P01.3, P01.4, P02, P09]
supersedes: []
superseded_by: null
---
# D39 — ownership-ci-gates (accepted)

**Actor:** author-choice (claude-opus-5-5/high, Claude Code, `task:plan-design … P01`, 2026-09-28) по мандату B6; реализует D18 и «ownership-гейт v2» skeleton P01.
**Evidence:** RAG:— `research/03-glossary.md` §Packages, §Kernel modules, §Root symbols, §Reserved segments, §Dev roots, §Identifiers per package; `artifacts/P01-design/facts/README.md` F5, F6; RAG:✅ `artifacts/research-rag/pplx-05-gha-supply-chain.md`, `artifacts/P01-design-rag/http-02-releases-postgres.md`.

## Solution

1. **Ownership gate v2** — `scripts/ownership-gate.php`: инвентарь идентификаторов `packages/*` шести видов (`namespace`, `table`, `config`, `publish-tag`, `command`, `env`) → пакет-владелец; правила O1 (один владелец на идентификатор), O2 (соответствие целевой конвенции glossary для пакета: корень namespace, префиксы config/tag/env/command), O3 (первые сегменты ядра ⊆ §Kernel modules ∪ §Root symbols ∪ §Reserved segments ∪ §Dev roots), O4 (таблица — владелец из `docs/architecture/table-ownership.md`). Целевая конвенция — `scripts/ownership-map.json`, сгенерированный из glossary; текущий composer-name → целевой пакет — через `package-map.tsv`, скопированный в `scripts/ownership-map.json`.
2. Режимы: по умолчанию **ratchet** — нарушение вне `scripts/ownership-gate-baseline.json` → exit 1, запись baseline без нарушения (устаревшая) → exit 1; `--strict` — baseline игнорируется; `--report` — JSON в stdout, exit 0. P01 фиксирует baseline текущих нарушений; P02 закрывается только при `--strict` exit 0 и пустом baseline.
3. **CI (D18):** все `uses:` — полный SHA + `# vX.Y.Z` (pinact), `permissions: {}` на уровне workflow и `contents: read` на job; job `zizmor` (zizmor-action, блокирующий, конфиг `.github/zizmor.yml` только с обоснованными ignore); `.github/dependabot.yml` (`github-actions` для `/`, `composer` для `/` и `/packages/*`, weekly, grouped); `.github/CODEOWNERS` `/.github/ @academici`, `/scripts/ @academici`; job `manifests` — `composer validate --strict --no-check-lock` для 21 манифеста; `composer audit --locked` после каждого install-шага; новые jobs — golden, ownership, per-package PHPStan + guard, dependency analyser.
4. SBOM (CycloneDX) и release-job — P09 (D19): release-job сейчас не существует.

## Why

- Существующие гейты покрывают namespace (R3) и таблицы (T1–T4), но не config/tag/command/env; коллизии имён — главный риск P02.
- Ratchet делает гейт блокирующим с первого дня без переименований в P01 (они — P02) и заставляет baseline только сокращаться.
- `@academici` — аутентифицированный владелец репозитория (`gh api user`, F5); замена на команду org — отдельное решение.

## Consequences

P01.3 — gate + baseline; P01.4 — CI; P02 — пустой baseline и `--strict`; P09 — SBOM/release.
