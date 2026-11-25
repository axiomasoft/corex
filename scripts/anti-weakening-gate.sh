#!/usr/bin/env bash
# Анти-ослабляющий гейт CI (P1.14, plan.md D34/D45; лейны/allowance — P2.15/D93).
#
# Ловит диффы, которые "чинят" красный гейт понижением планки вместо фикса:
#   - ->skip(/markTestSkipped в тестовых файлах (tests/) — тест выключен, а не исправлен;
#   - continue-on-error в workflow'ах — job формально зелёный при красном шаге;
#   - рост измеренного потолка PHPStan/dependencies или ownership baseline;
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

# PLAN3.D37/D39: bind every change to its preceding committed debt ceiling.
# Initial introduction is permitted once; later additions are rejected even when
# the remote comparison base predates creation. Include uncommitted changes.
python3 - "$BASE_REF" <<'PYRATCHET' || log_fail "quality ceilings or ownership baseline grew"
import json, pathlib, subprocess, sys, re
base = sys.argv[1]
paths = {'scripts/quality-ceilings.json': 'ceilings', 'scripts/ownership-gate-baseline.json': 'ownership',
         'scripts/schema-gate-pending.json': 'inventory', 'scripts/schema-gate-allowlist.json': 'inventory',
         'scripts/schema-golden-name-pending.json': 'inventory'}
def read(ref, path):
    if ref is None:
        p = pathlib.Path(path)
        return json.loads(p.read_text()) if p.exists() else None
    result = subprocess.run(['git', 'show', f'{ref}:{path}'], capture_output=True, text=True)
    if result.returncode:
        check = subprocess.run(['git', 'ls-tree', '--name-only', ref, '--', path], capture_output=True, text=True)
        if check.returncode or check.stdout.strip():
            raise RuntimeError('cannot read ' + path)
        return None
    return json.loads(result.stdout)
def check(old, new, kind):
    if new is None:
        return kind == 'inventory' or old is None
    if old is None:
        return True
    if kind == 'inventory':
        return not (set(new) - set(old))
    succ_path = pathlib.Path('scripts/ratchet-successors.json')
    succ = json.loads(succ_path.read_text()) if succ_path.exists() else {'groups': [], 'packages': {}}
    if kind == 'ownership':
        def project(value):
            for name in sorted(succ['packages'], key=len, reverse=True):
                value = re.sub(r'(?<![\w/-])' + re.escape(name) + r'(?![\w/-])', succ['packages'][name], value)
            return value
        return not (set(new['violations']) - {project(v) for v in old['violations']})
    for category in ['phpstan', 'dependencies']:
        before, after = dict(old[category]), new[category]
        projection = succ.get('source_transfers', {})
        if all(k in before for k in projection.get('when_before_contains', ['__absent__'])):
            for transfer in projection['entries']:
                amount = transfer.get(category, 0)
                before[transfer['from']] -= amount
                before[transfer['to']] = before.get(transfer['to'], 0) + amount
        grouped = set()
        for group in succ['groups']:
            if not all(k in before for k in group['before']) or any(k in before for k in group['after'] if k not in group['before']):
                continue
            if sum(after.get(k, 0) for k in group['after']) > sum(before.get(k, 0) for k in group['before']):
                return False
            grouped.update(group['after'])
        if any(value > before.get(package, 0) for package, value in after.items() if package not in grouped):
            return False
        if sum(after.values()) > sum(before.values()):
            return False
    return True
failed = False
for path, kind in paths.items():
    commits = subprocess.check_output(['git', 'rev-list', '--reverse', (base + '..HEAD') if base else 'HEAD', '--', path], text=True).splitlines()
    previous = read(base, path) if base else None
    for commit in commits:
        current = read(commit, path)
        if not check(previous, current, kind):
            print(f'ratchet FAIL: {path} at {commit}', file=sys.stderr)
            failed = True
        previous = current
    current = read(None, path)
    if not check(read('HEAD', path), current, kind):
        print(f'ratchet FAIL: {path} in worktree', file=sys.stderr)
        failed = True
sys.exit(1 if failed else 0)
PYRATCHET

# Bind each baseline change to its own rebaseline entry. A historical initial
# entry in the remote-base diff must not authorize unrelated future changes.
# A pushed commit cannot be amended without rewriting shared history, so an
# entry added later on the branch also counts when it names that commit's full
# sha. One entry authorizes exactly one change: an entry that names a commit
# counts only for that commit, and one naming several commits counts for none.
rebaseline_entries() {
    command grep -E '^\+\| [0-9]{4}-' || true
}
entry_shas() {
    command grep -oE '[0-9a-f]{40}' <<<"$1" | sort -u || true
}
check_golden_rebaseline() {
    local before="$1" after="${2:-}" line shas authorized=0
    git diff --name-only "$before" ${after:+"$after"} -- tests/Golden/baseline | command grep -q . || return 0
    local own=""
    [ -n "$after" ] && own="$(git rev-parse "$after")"
    # Entries added by the change itself, unless they name another commit.
    while IFS= read -r line; do
        [ -n "$line" ] || continue
        shas="$(entry_shas "$line")"
        if [ -z "$shas" ] || [ "$shas" = "$own" ]; then
            authorized=1
        fi
    done < <(git diff "$before" ${after:+"$after"} -- tests/Golden/REBASELINE.md | rebaseline_entries)
    # A later entry on the branch that names exactly this commit.
    if [ "$authorized" = 0 ] && [ -n "$own" ]; then
        while IFS= read -r line; do
            [ -n "$line" ] || continue
            [ "$(entry_shas "$line")" = "$own" ] && authorized=1
        done < <(git diff "$BASE_REF" HEAD -- tests/Golden/REBASELINE.md | rebaseline_entries)
    fi
    if [ "$authorized" = 0 ]; then
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
    for pkg_tests in "$worktree"/packages/*/tests "$worktree"/products/*/tests; do
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
