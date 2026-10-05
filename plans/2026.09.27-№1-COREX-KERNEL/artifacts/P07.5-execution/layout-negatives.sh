#!/usr/bin/env bash
# Old kernel enum must R1 the ten Scheduling files; an unknown module must R1. Restores the tree.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
layout=scripts/module-layout.json
original="$(mktemp)"
cp "$layout" "$original"
restore_layout() { cp "$original" "$layout"; rm -f "$original"; }
trap restore_layout EXIT
python3 - <<'PY'
import json
from pathlib import Path
path = Path("scripts/module-layout.json")
data = json.loads(path.read_text())
data["kernel_modules"].remove("Scheduling")
path.write_text(json.dumps(data, indent=4, ensure_ascii=False) + "\n")
PY
set +e
python3 scripts/module-layout-gate.py > /tmp/p075-module-layout-old-enum.txt
old_ec=$?
set -e
restore_layout
trap - EXIT
test "$old_ec" -ne 0
files=(
  packages/core/src/Scheduling/Contracts/WakeupScheduler.php
  packages/core/src/Scheduling/Contracts/DueWakeupSource.php
  packages/core/src/Scheduling/Contracts/DueWakeupHandler.php
  packages/core/src/Scheduling/Data/WakeupRequest.php
  packages/core/src/Scheduling/Data/WakeupWindow.php
  packages/core/src/Scheduling/Data/WakeupDelivery.php
  packages/core/src/Scheduling/Data/WakeupHandlerDefinition.php
  packages/core/src/Scheduling/Data/ScheduleResult.php
  packages/core/src/Scheduling/Data/CancelResult.php
  packages/core/src/Scheduling/Data/SweepResult.php
)
for file in "${files[@]}"; do
  command grep -F "new R1 ${file}" /tmp/p075-module-layout-old-enum.txt >/dev/null
done
probe=packages/core/src/UnknownModule
rm -rf "$probe"
mkdir -p "$probe"
printf '%s\n' '<?php' '' 'namespace CoreX\UnknownModule;' '' 'final class Probe {}' > "$probe/Probe.php"
cleanup_probe() { rm -rf "$probe"; }
trap cleanup_probe EXIT
set +e
python3 scripts/module-layout-gate.py > /tmp/p075-module-layout-unknown.txt
unk_ec=$?
set -e
cleanup_probe
trap - EXIT
test "$unk_ec" -ne 0
command grep -F "packages/core/src/UnknownModule/Probe.php" /tmp/p075-module-layout-unknown.txt >/dev/null
scripts/php84 composer module-layout-gate
git diff --exit-code -- "$layout"
test ! -e "$probe"
