#!/usr/bin/env bash
# Plant a notifications → wakeup import, require the forbidden edge, then restore a green gate.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
file=packages/notifications/src/Digest/Internal/DigestTick.php
backup="$(mktemp)"
cp "$file" "$backup"
restore() { cp "$backup" "$file"; rm -f "$backup"; }
trap restore EXIT
python3 - <<'PY'
from pathlib import Path
path = Path("packages/notifications/src/Digest/Internal/DigestTick.php")
needle = "use CoreX\\Scheduling\\Data\\WakeupWindow;\n"
insert = needle + "use CoreX\\Wakeup\\Contracts\\DueWakeupSweeper;\n"
text = path.read_text()
if needle not in text:
    raise SystemExit("DigestTick anchor missing")
path.write_text(text.replace(needle, insert, 1))
PY
set +e
scripts/php84 php scripts/package-graph-gate.php > /tmp/p075-package-graph-negative.txt 2>&1
negative_ec=$?
set -e
command grep -F "axiomasoft/corex-notifications → axiomasoft/corex-wakeup" /tmp/p075-package-graph-negative.txt >/dev/null
command grep -F "DigestTick.php" /tmp/p075-package-graph-negative.txt >/dev/null
test "$negative_ec" -ne 0
restore
trap - EXIT
git diff --exit-code -- "$file"
scripts/php84 php scripts/package-graph-gate.php
