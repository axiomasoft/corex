"""Exercise the actual gate in an isolated Git repository with immutable ranges."""
from pathlib import Path
import subprocess
import tempfile

repo_root = Path(__file__).resolve().parents[4]
gate = repo_root / "scripts/contract-guard.sh"

with tempfile.TemporaryDirectory(prefix="corex-contract-guard-") as copy:
    root = Path(copy)

    def git(*args):
        return subprocess.check_output(["git", *args], cwd=root, text=True).strip()

    git("init", "-q")
    git("config", "user.name", "contract-guard control")
    git("config", "user.email", "test@corex.local")
    surface = root / "packages/probe/resources/contracts/php-api.json"
    surface.parent.mkdir(parents=True)
    surface.write_text('{}\n')
    changelog = root / "packages/probe/CHANGELOG.md"
    changelog.write_text("# Changelog\n")
    git("add", ".")
    git("commit", "-qm", "base")
    base = git("rev-parse", "HEAD")

    surface.write_text('{"symbols": {}}\n')
    git("add", ".")
    git("commit", "-qm", "surface without announcement")
    result = subprocess.run(["bash", str(gate), base], cwd=root, capture_output=True, text=True)
    assert result.returncode == 1, result.stdout + result.stderr
    assert "WITHOUT packages/probe/CHANGELOG.md" in result.stdout
    print("negative: changed snapshot without CHANGELOG -> exit 1")

    changelog.write_text("# Changelog\n\n## Unreleased\n\n- Added declared PHP symbols.\n")
    git("add", ".")
    git("commit", "-qm", "announce surface")
    result = subprocess.run(["bash", str(gate), base], cwd=root, capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
    assert "changed WITH CHANGELOG" in result.stdout
    print("positive: same snapshot with CHANGELOG -> exit 0")

    unchanged = git("rev-parse", "HEAD")
    implementation = root / "packages/probe/src/Internal/Probe.php"
    implementation.parent.mkdir(parents=True)
    implementation.write_text("<?php // implementation only\n")
    git("add", ".")
    git("commit", "-qm", "implementation only")
    result = subprocess.run(["bash", str(gate), unchanged], cwd=root, capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
    assert "no resources/contracts/php-api.json changes" in result.stdout
    print("control: implementation-only change -> exit 0")
