#!/usr/bin/env bash
# Анти-ослабляющий гейт CI (P1.14, plan.md D34/D45; лейны/allowance — P2.15/D93).
#
# Ловит диффы, которые "чинят" красный гейт понижением планки вместо фикса:
#   - ->skip(/markTestSkipped в тестовых файлах (tests/) — тест выключен, а не исправлен;
#   - continue-on-error в workflow'ах — job формально зелёный при красном шаге;
#   - phpstan-baseline (или сам файл baseline.neon) — скрытый долг вместо фикса ошибки;
#   - понижение --min= в composer test:coverage;
#   - уменьшение числа тестов в любом из двух лейнов (sqlite/pg) относительно базы —
#     число считается ПО ОБЪЕДИНЕНИЮ корневого `tests/` и всех `packages/*/tests`
#     (пакетные сьюты D81 — иначе их вынос из корня читается как удаление тестов, D93).
#
# Сравнивает HEAD с базой: merge-base с origin/main, либо (fallback) родитель
# HEAD, либо (первый коммит истории) пропускает численные проверки — не с чем
# сравнивать. Одобренное владельцем уменьшение числа тестов (напр. снос легаси-каркаса
# вместе с его тестами) смягчается ТОЛЬКО через `scripts/anti-weakening-allowances.txt`,
# ЯКОРЕННЫЙ на конкретный base sha — скидка авто-истекает, как только BASE_REF уезжает
# (постоянный floor намеренно отвергнут — D93, он тихо накапливал бы вычет навсегда).
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

# Bind each baseline change to its own rebaseline entry. A historical initial
# entry in the remote-base diff must not authorize unrelated future changes.
check_golden_rebaseline() {
    local before="$1" after="${2:-}"
    if git diff --name-only "$before" ${after:+"$after"} -- tests/Golden/baseline | command grep -q . \
       && ! git diff "$before" ${after:+"$after"} -- tests/Golden/REBASELINE.md | command grep -qE '^\+\| [0-9]{4}-'; then
        log_fail "golden baseline changed without a REBASELINE.md entry ($before ${after:-worktree})"
    fi
}
if [ -n "$BASE_REF" ]; then
    while read -r golden_commit; do
        check_golden_rebaseline "$golden_commit^" "$golden_commit"
    done < <(git rev-list "$DIFF_RANGE" -- tests/Golden/baseline)
fi
check_golden_rebaseline HEAD

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

    # D47-floor: owner-одобренное понижение --min (80->55, 2026-07-14) не должно
    # перефлагиваться на КАЖДОМ item'е волны только потому, что origin/main ещё несёт
    # исторический 80 — база сравнения для ЭТОЙ проверки заменяется на floor, если он
    # выше "сырого" OLD_MIN из origin/main (т.е. floor только смягчает, никогда не
    # снижает защиту ниже уже одобренного порога).
    if [ -f "scripts/coverage-min-floor.txt" ]; then
        FLOOR_MIN="$(command grep -o '^[0-9]*' scripts/coverage-min-floor.txt | head -1)"
        if [ -n "$FLOOR_MIN" ] && { [ -z "$OLD_MIN" ] || [ "$FLOOR_MIN" -lt "$OLD_MIN" ]; }; then
            OLD_MIN="$FLOOR_MIN"
        fi
    fi

    if [ -n "$OLD_MIN" ] && [ -n "$NEW_MIN" ] && [ "$NEW_MIN" -lt "$OLD_MIN" ]; then
        log_fail "coverage --min= понижен: $OLD_MIN -> $NEW_MIN"
    fi
fi

# --- 3. Число тестов — только вверх (sqlite-лейн исключая ->group('pg'), pg-лейн) ---
# База сравнения = объединение корневого tests/ и ВСЕХ packages/*/tests (D81/D93):
# считать только корень значило бы читать миграцию сьюта в пакет как удаление тестов.
count_tests() {
    local ref="$1"
    local worktree
    worktree="$(mktemp -d)"
    git worktree add --quiet --detach "$worktree" "$ref" >/dev/null 2>&1 || {
        rm -rf "$worktree"
        echo "0 0"
        return
    }

    local test_dirs=()
    [ -d "$worktree/tests" ] && test_dirs+=("$worktree/tests")
    local pkg_tests
    for pkg_tests in "$worktree"/packages/*/tests; do
        [ -d "$pkg_tests" ] && test_dirs+=("$pkg_tests")
    done

    local total=0 pg=0
    if [ "${#test_dirs[@]}" -gt 0 ]; then
        total="$(command grep -rEo "^\s*(it|test)\(|public function test_" "${test_dirs[@]}" 2>/dev/null | wc -l | tr -d ' ')"
        # `command` НЕ ставится перед grep здесь: xargs исполняет свой аргумент как
        # программу через exec(), а не через шелл, где резолвился бы алиас/функция —
        # `command grep` в этой позиции был вызовом несуществующего файла (pg-счёт
        # всегда 0, D93 причина 2). Прямые вызовы `command grep` вне xargs (выше и
        # в блоке 1) защиту от алиасов сохраняют — это shell-builtin, не программа.
        pg="$(command grep -rl "group('pg')" "${test_dirs[@]}" 2>/dev/null | xargs -r grep -Eo "^\s*(it|test)\(|public function test_" 2>/dev/null | wc -l | tr -d ' ')"
    fi

    git worktree remove --force "$worktree" >/dev/null 2>&1 || true

    echo "$total $pg"
}

# Allowance — одобренное владельцем уменьшение числа тестов лейна, ЯКОРЕННОЕ на base
# sha (D93): скидка применяется ТОЛЬКО пока текущий BASE_REF равен sha строки (полное
# сравнение через `git rev-parse`, не префиксы); иная/неизвестная база → строка печатается
# «неактивной» и не смягчает счёт (fail-closed к строгости, скидки не накапливаются вечно).
ALLOWANCES_FILE="scripts/anti-weakening-allowances.txt"

apply_allowance() {
    local lane="$1"
    ALLOWANCE_DELTA=0

    [ -f "$ALLOWANCES_FILE" ] || return 0

    local line
    while IFS= read -r line || [ -n "$line" ]; do
        case "$line" in
            ''|'#'*) continue ;;
        esac

        local a_sha a_lane a_delta a_issue a_reason
        a_reason=""
        read -r a_sha a_lane a_delta a_issue a_reason <<< "$line"

        if [ -z "$a_sha" ] || [ -z "$a_lane" ] || [ -z "$a_delta" ] || [ -z "$a_issue" ]; then
            log_fail "allowance-строка не разбирается позиционно (ожидается '<sha> <lane> <дельта> <D#> <причина>'): '$line'"
            continue
        fi
        if [ "$a_lane" != "sqlite" ] && [ "$a_lane" != "pg" ]; then
            log_fail "allowance-строка несёт неизвестный лейн '$a_lane': '$line'"
            continue
        fi
        case "$a_delta" in
            -[0-9]*) : ;;
            *)
                log_fail "allowance-строка несёт не отрицательную дельту (запрещено — скидка только вниз): '$line'"
                continue
                ;;
        esac

        [ "$a_lane" = "$lane" ] || continue

        local a_sha_full=""
        a_sha_full="$(git rev-parse --verify --quiet "$a_sha" 2>/dev/null || true)"

        if [ -n "$a_sha_full" ] && [ -n "$BASE_REF" ] && [ "$a_sha_full" = "$BASE_REF" ]; then
            ALLOWANCE_DELTA=$((ALLOWANCE_DELTA + a_delta))
            echo "ANTI-WEAKENING GATE: allowance применена ($lane $a_delta, $a_issue) — база совпадает $a_sha"
        else
            echo "ANTI-WEAKENING GATE: allowance НЕАКТИВНА ($lane $a_delta, $a_issue, sha=$a_sha) — база сравнения не совпадает"
        fi
    done < "$ALLOWANCES_FILE"
}

if [ -n "$BASE_REF" ]; then
    read -r OLD_TOTAL OLD_PG <<< "$(count_tests "$BASE_REF")"
    read -r NEW_TOTAL NEW_PG <<< "$(count_tests HEAD)"

    OLD_SQLITE=$((OLD_TOTAL - OLD_PG))
    NEW_SQLITE=$((NEW_TOTAL - NEW_PG))

    apply_allowance sqlite
    OLD_SQLITE_ALLOWED=$((OLD_SQLITE + ALLOWANCE_DELTA))
    apply_allowance pg
    OLD_PG_ALLOWED=$((OLD_PG + ALLOWANCE_DELTA))

    if [ "$NEW_SQLITE" -lt "$OLD_SQLITE_ALLOWED" ]; then
        log_fail "число тестов sqlite-лейна уменьшилось: $OLD_SQLITE -> $NEW_SQLITE (после allowance: $OLD_SQLITE_ALLOWED)"
    fi
    if [ "$NEW_PG" -lt "$OLD_PG_ALLOWED" ]; then
        log_fail "число тестов pg-лейна уменьшилось: $OLD_PG -> $NEW_PG (после allowance: $OLD_PG_ALLOWED)"
    fi

    echo "ANTI-WEAKENING GATE: тесты sqlite $OLD_SQLITE -> $NEW_SQLITE, pg $OLD_PG -> $NEW_PG"
fi

if [ "$fail" -ne 0 ]; then
    echo "ANTI-WEAKENING GATE: FAILED" >&2
    exit 1
fi

echo "ANTI-WEAKENING GATE: OK"
