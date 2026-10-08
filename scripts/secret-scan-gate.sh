#!/usr/bin/env bash
# Secret scan gate (audit 2026-10-08, SEC-01): gitleaks over the full git history plus staged
# staged, unstaged and non-ignored untracked files. Policy: .gitleaks.toml and
# .gitleaksignore (reviewed fingerprints only). Needs the full history (CI: fetch-depth 0).
# Never prints secret values (--redact). Missing or too old gitleaks fails closed.
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

bin="${GITLEAKS_BIN:-gitleaks}"
min="8.25.0" # [[rules.allowlists]] / condition syntax used by .gitleaks.toml

if ! command -v "$bin" >/dev/null 2>&1; then
    echo "secret-scan-gate: gitleaks >= ${min} not found (set GITLEAKS_BIN or install it)" >&2
    exit 2
fi

version="$("$bin" version | tr -d 'v[:space:]')"
if [[ "$(printf '%s\n%s\n' "$min" "$version" | sort -V | head -n1)" != "$min" ]]; then
    echo "secret-scan-gate: gitleaks ${version} is older than ${min}" >&2
    exit 2
fi

if [[ "$(git rev-parse --is-shallow-repository)" == "true" ]]; then
    echo "secret-scan-gate: shallow clone; history scan needs fetch-depth 0" >&2
    exit 2
fi

export GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=diff.renameLimit GIT_CONFIG_VALUE_0=10000

scan() {
    "$bin" git --no-banner --redact --verbose --config .gitleaks.toml --gitleaks-ignore-path .gitleaksignore "$@" .
}

scan --log-level warn
scan --log-level warn --pre-commit --staged
scan --log-level warn --pre-commit

# git diff does not include untracked files. Scan their bytes under their original
# relative names, excluding git-ignored local dependencies, credentials and output.
# Use a temporary tree so dir mode cannot walk vendor/ or machine-local dotfiles.
python3 - "$bin" <<'PY'
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

root = Path.cwd()
binary = shutil.which(sys.argv[1])
if binary is None:
    raise SystemExit(2)
binary = str(Path(binary).resolve())
paths = subprocess.check_output(['git', 'ls-files', '--others', '--exclude-standard', '-z'])
with tempfile.TemporaryDirectory(prefix='corex-secret-scan-') as temporary:
    snapshot = Path(temporary)
    for entry in paths.split(b'\0'):
        if not entry:
            continue
        relative = Path(os.fsdecode(entry))
        source = root / relative
        if source.is_symlink():
            raise SystemExit('secret-scan-gate: untracked symlink requires explicit review: ' + str(relative))
        target = snapshot / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(source, target)
    subprocess.run([binary, 'dir', '--no-banner', '--redact', '--verbose', '--log-level', 'warn',
                    '--config', str(root / '.gitleaks.toml'), '--gitleaks-ignore-path',
                    str(root / '.gitleaksignore'), '.'], cwd=snapshot, check=True)
PY

echo "secret-scan-gate: OK (gitleaks ${version})"
