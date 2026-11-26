#!/usr/bin/env bash
# Compare branch history with the integration base. CI supplies the previous
# main tip on main pushes and the target branch SHA on pull requests.
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

fail=0

log_fail() {
    echo "ANTI-WEAKENING GATE: $1" >&2
    fail=1
}

requested_base="${1:-}"
if [[ -n "$requested_base" && ! "$requested_base" =~ ^0+$ ]]; then
    BASE_REF="$(git rev-parse --verify --end-of-options "${requested_base}^{commit}")"
    git merge-base --is-ancestor "$BASE_REF" HEAD || {
        echo "The comparison base must be an ancestor of HEAD." >&2
        exit 1
    }
else
    BASE_REF="$(git merge-base HEAD origin/main 2>/dev/null || true)"
    if [[ -z "$BASE_REF" || "$BASE_REF" = "$(git rev-parse HEAD)" ]]; then
        BASE_REF="$(git rev-parse --verify HEAD^ 2>/dev/null || true)"
    fi
fi
if [[ -n "$BASE_REF" ]]; then
    echo "Anti-weakening comparison base: $BASE_REF"
    DIFF_RANGE="$BASE_REF..HEAD"
else
    echo "Initial commit has no prior ceilings."
    DIFF_RANGE=HEAD
fi

check_added_pattern() {
    local pattern="$1"
    local label="$2"
    shift 2

    local hits
    hits="$(git diff "$DIFF_RANGE" -- "$@" | command grep -E '^\+' | command grep -E -v '^\+\+\+' | command grep -F -- "$pattern" || true)"

    if [ -n "$hits" ]; then
        log_fail "$label added in the diff:"
        echo "$hits" >&2
    fi
}

test_paths=(':(glob)tests/**/*.php' ':(glob)packages/*/tests/**/*.php' ':(glob)products/*/tests/**/*.php')
check_added_pattern '->skip(' "->skip(" "${test_paths[@]}"
check_added_pattern 'markTestSkipped' 'markTestSkipped' "${test_paths[@]}"
check_added_pattern 'continue-on-error' 'continue-on-error' ':(glob).github/workflows/*.yml' ':(glob).github/workflows/*.yaml'

# Bind every change to its preceding committed debt ceiling.
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

python3 "$(dirname "$0")/check-golden-history.py" "$BASE_REF" || log_fail "golden snapshot history changed without a receipt"

# The historical Composer flag is only a fallback for bases without a floor file.
floor_file=scripts/coverage-min-floor.txt
read_floor() {
    local value
    IFS= read -r value || [[ -n "$value" ]] || return 1
    [[ "$value" =~ ^(0|[1-9][0-9]?|100)$ ]] || return 1
    echo "$value"
}
floor_at() {
    local ref="$1"
    if git cat-file -e "$ref:$floor_file" 2>/dev/null; then
        read_floor < <(git show "$ref:$floor_file")
    else
        git show "$ref:composer.json" 2>/dev/null | sed -nE '/"test:coverage":/s/.*--min=([0-9]+).*/\1/p' || true
    fi
}
check_floor() {
    local old="$1" new="$2" where="$3"
    if [[ -z "$new" ]]; then
        log_fail "root coverage floor missing or invalid at $where"
    elif [[ -n "$old" && "$new" -lt "$old" ]]; then
        log_fail "root coverage floor lowered at $where: $old -> $new"
    fi
}
if [[ -n "$BASE_REF" ]]; then
    while read -r revision parents; do
        [[ -n "$revision" ]] || continue
        new_min="$(read_floor < <(git show "$revision:$floor_file" 2>/dev/null) || true)"
        for parent in $parents; do
            old_min="$(floor_at "$parent")"
            check_floor "$old_min" "$new_min" "$revision"
        done
    done < <(git log --full-history --format='%H %P' "$DIFF_RANGE" -- "$floor_file")
fi
head_min="$(read_floor < <(git show "HEAD:$floor_file" 2>/dev/null) || true)"
work_min="$(read_floor < "$floor_file" || true)"
base_min=""
if [[ -n "$BASE_REF" ]]; then
    base_min="$(floor_at "$BASE_REF")"
fi
check_floor "$base_min" "$head_min" HEAD
check_floor "$head_min" "$work_min" worktree

# Git reads the committed test trees directly, including filenames with spaces.
count_tests() {
    python3 - "$1" <<'PYCOUNT'
import subprocess, sys

revision = sys.argv[1]
paths = [':(glob)tests/**/*.php', ':(glob)packages/*/tests/**/*.php', ':(glob)products/*/tests/**/*.php']
def matches(*options):
    result = subprocess.run(['git', 'grep', *options, revision, '--', *paths], capture_output=True)
    if result.returncode not in (0, 1):
        sys.exit(result.stderr.decode())
    return result.stdout

pg_files = set(matches('-lzF', "group('pg')").split(b'\0'))
total = pg = 0
results = matches('-zcE', '^[[:space:]]*(it|test)\\(|public function test_')
while results:
    name, _, remainder = results.partition(b'\0')
    value, _, results = remainder.partition(b'\n')
    count = int(value)
    total += count
    if name in pg_files:
        pg += count
print(total, pg)
PYCOUNT
}

# A retired test allowance applies only to the exact base it was reviewed against.
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
            log_fail "expected allowance format '<sha> <lane> <delta> <decision> <reason>': '$line'"
            continue
        fi
        if [ "$a_lane" != "sqlite" ] && [ "$a_lane" != "pg" ]; then
            log_fail "unknown allowance lane '$a_lane': '$line'"
            continue
        fi
        case "$a_delta" in
            -[0-9]*) : ;;
            *)
                log_fail "expected a negative allowance delta: '$line'"
                continue
                ;;
        esac

        [ "$a_lane" = "$lane" ] || continue

        local a_sha_full=""
        a_sha_full="$(git rev-parse --verify --quiet "$a_sha" 2>/dev/null || true)"

        if [ -n "$a_sha_full" ] && [ -n "$BASE_REF" ] && [ "$a_sha_full" = "$BASE_REF" ]; then
            ALLOWANCE_DELTA=$((ALLOWANCE_DELTA + a_delta))
            echo "ANTI-WEAKENING GATE: allowance applied ($lane $a_delta, $a_issue); matching base $a_sha"
        else
            echo "ANTI-WEAKENING GATE: allowance inactive ($lane $a_delta, $a_issue, sha=$a_sha); different base"
        fi
    done < "$ALLOWANCES_FILE"
}

if [ -n "$BASE_REF" ]; then
    base_counts="$(count_tests "$BASE_REF")"
    head_counts="$(count_tests HEAD)"
    read -r OLD_TOTAL OLD_PG <<< "$base_counts"
    read -r NEW_TOTAL NEW_PG <<< "$head_counts"

    OLD_SQLITE=$((OLD_TOTAL - OLD_PG))
    NEW_SQLITE=$((NEW_TOTAL - NEW_PG))

    apply_allowance sqlite
    OLD_SQLITE_ALLOWED=$((OLD_SQLITE + ALLOWANCE_DELTA))
    apply_allowance pg
    OLD_PG_ALLOWED=$((OLD_PG + ALLOWANCE_DELTA))

    if [ "$NEW_SQLITE" -lt "$OLD_SQLITE_ALLOWED" ]; then
        log_fail "SQLite test count fell: $OLD_SQLITE -> $NEW_SQLITE (after allowance: $OLD_SQLITE_ALLOWED)"
    fi
    if [ "$NEW_PG" -lt "$OLD_PG_ALLOWED" ]; then
        log_fail "PostgreSQL test count fell: $OLD_PG -> $NEW_PG (after allowance: $OLD_PG_ALLOWED)"
    fi

    echo "ANTI-WEAKENING GATE: tests SQLite $OLD_SQLITE -> $NEW_SQLITE, pg $OLD_PG -> $NEW_PG"
fi

if [ "$fail" -ne 0 ]; then
    echo "ANTI-WEAKENING GATE: FAILED" >&2
    exit 1
fi

echo "ANTI-WEAKENING GATE: OK"
