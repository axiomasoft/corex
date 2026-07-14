#!/usr/bin/env bash
# Анти-ослабляющий гейт CI (P1.14, plan.md D34/D45).
#
# Ловит диффы, которые "чинят" красный гейт понижением планки вместо фикса:
#   - ->skip(/markTestSkipped в тестовых файлах (tests/) — тест выключен, а не исправлен;
#   - continue-on-error в workflow'ах — job формально зелёный при красном шаге;
#   - phpstan-baseline (или сам файл baseline.neon) — скрытый долг вместо фикса ошибки;
#   - понижение --min= в composer test:coverage;
#   - уменьшение числа тестов в любом из двух лейнов (sqlite/pg) относительно базы.
#
# Сравнивает HEAD с базой: merge-base с origin/main, либо (fallback) родитель
# HEAD, либо (первый коммит истории) пропускает численные проверки — не с чем
# сравнивать.
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

fail=0

log_fail() {
    echo "ANTI-WEAKENING GATE: $1" >&2
    fail=1
}

git fetch origin main --quiet 2>/dev/null || true

BASE_REF=""
if git rev-parse --verify --quiet origin/main >/dev/null; then
    BASE_REF="$(git merge-base HEAD origin/main 2>/dev/null || true)"
fi
if [ -z "$BASE_REF" ]; then
    BASE_REF="$(git rev-parse HEAD^ 2>/dev/null || true)"
fi

if [ -z "$BASE_REF" ]; then
    echo "ANTI-WEAKENING GATE: нет базы для сравнения (первый коммит истории) — численные проверки пропущены." >&2
else
    echo "ANTI-WEAKENING GATE: база сравнения = $BASE_REF"
fi

# --- 1. Запрещённые паттерны в добавленных строках диффа (тесты + workflow'ы) ---
if [ -n "$BASE_REF" ]; then
    DIFF_RANGE="$BASE_REF..HEAD"
else
    DIFF_RANGE="HEAD"
fi

check_added_pattern() {
    local pattern="$1"
    local label="$2"
    local pathspec="$3"

    local hits
    hits="$(git diff "$DIFF_RANGE" -- $pathspec | command grep -E '^\+' | command grep -E -v '^\+\+\+' | command grep -F -- "$pattern" || true)"

    if [ -n "$hits" ]; then
        log_fail "$label добавлен диффом:"
        echo "$hits" >&2
    fi
}

check_added_pattern '->skip(' "->skip(" 'tests/*.php tests/**/*.php'
check_added_pattern 'markTestSkipped' 'markTestSkipped' 'tests/*.php tests/**/*.php'
check_added_pattern 'continue-on-error' 'continue-on-error' '.github/workflows/*.yml'

# phpstan-baseline: запрет как ключа includes в phpstan.neon, так и появления файла.
if git diff "$DIFF_RANGE" -- phpstan.neon | command grep -E '^\+' | command grep -qi 'baseline'; then
    log_fail "phpstan-baseline добавлен в phpstan.neon (скрытый долг запрещён, D34/Code Guidance P1.14)"
fi
if git diff --name-status "$DIFF_RANGE" 2>/dev/null | awk '$1 == "A" {print $2}' | command grep -qi 'baseline\.neon$'; then
    log_fail "новый файл *baseline.neon добавлен диффом (скрытый долг запрещён)"
fi

# --- 2. --min= в composer.json не понижен ---
extract_min() {
    local ref="$1"
    git show "$ref:composer.json" 2>/dev/null \
        | command grep -o '"test:coverage": *"[^"]*"' \
        | command grep -o -- '--min=[0-9]*' \
        | command grep -o '[0-9]*' \
        || true
}

if [ -n "$BASE_REF" ]; then
    OLD_MIN="$(extract_min "$BASE_REF")"
    NEW_MIN="$(extract_min HEAD)"

    if [ -n "$OLD_MIN" ] && [ -n "$NEW_MIN" ] && [ "$NEW_MIN" -lt "$OLD_MIN" ]; then
        log_fail "coverage --min= понижен: $OLD_MIN -> $NEW_MIN"
    fi
fi

# --- 3. Число тестов — только вверх (sqlite-лейн исключая ->group('pg'), pg-лейн) ---
count_tests() {
    local ref="$1"
    local worktree
    worktree="$(mktemp -d)"
    git worktree add --quiet --detach "$worktree" "$ref" >/dev/null 2>&1 || {
        rm -rf "$worktree"
        echo "0 0"
        return
    }

    local total pg
    total="$(command grep -rEo "^\s*(it|test)\(|public function test_" "$worktree/tests" 2>/dev/null | wc -l | tr -d ' ')"
    pg="$(command grep -rl "group('pg')" "$worktree/tests" 2>/dev/null | xargs -r command grep -Eo "^\s*(it|test)\(|public function test_" 2>/dev/null | wc -l | tr -d ' ')"

    git worktree remove --force "$worktree" >/dev/null 2>&1 || true

    echo "$total $pg"
}

if [ -n "$BASE_REF" ]; then
    read -r OLD_TOTAL OLD_PG <<< "$(count_tests "$BASE_REF")"
    read -r NEW_TOTAL NEW_PG <<< "$(count_tests HEAD)"

    OLD_SQLITE=$((OLD_TOTAL - OLD_PG))
    NEW_SQLITE=$((NEW_TOTAL - NEW_PG))

    if [ "$NEW_SQLITE" -lt "$OLD_SQLITE" ]; then
        log_fail "число тестов sqlite-лейна уменьшилось: $OLD_SQLITE -> $NEW_SQLITE"
    fi
    if [ "$NEW_PG" -lt "$OLD_PG" ]; then
        log_fail "число тестов pg-лейна уменьшилось: $OLD_PG -> $NEW_PG"
    fi

    echo "ANTI-WEAKENING GATE: тесты sqlite $OLD_SQLITE -> $NEW_SQLITE, pg $OLD_PG -> $NEW_PG"
fi

if [ "$fail" -ne 0 ]; then
    echo "ANTI-WEAKENING GATE: FAILED" >&2
    exit 1
fi

echo "ANTI-WEAKENING GATE: OK"
