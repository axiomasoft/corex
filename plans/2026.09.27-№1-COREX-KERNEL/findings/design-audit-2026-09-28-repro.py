#!/usr/bin/env python3
"""Read-only reproduction for PLAN3 design audit 2026-09-28 (B1, B3).

Does not write tests/Golden/rename-map.json or any product file.
Run from the repository root.
"""
from __future__ import annotations

import importlib.util
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
PLAN = ROOT / "plans/2026.09.27-№1-COREX-KERNEL"


def load_generator():
    path = PLAN / "artifacts/P01-design/scripts/golden-rename-map.py"
    spec = importlib.util.spec_from_file_location("golden_rename_map", path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def main() -> int:
    document = load_generator().build([])
    classes = [rule for rule in document["generated"] if rule["kind"] == "class"]
    packages = [rule for rule in document["generated"] if rule["kind"] == "package"]
    sample = classes[0]
    print(
        f"B1 rename-map: {len(classes)} class, {len(packages)} package, "
        f"{len(document['ambiguous_packages'])} ambiguous"
    )
    print(f"B1 sample from={sample['from']} to={sample['to']}")
    print(f"B1 equal_at_p01={sample['to'] == sample['from']}")

    checks = [
        ["grep", "-c", "stancl-providers", "composer.json", "packages/tenancy/composer.json"],
        ["grep", "-nE", "'8\\.3'|Boxed profile"],
    ]
    pinned = subprocess.run(
        ["grep", "-vcE", r"@[0-9a-f]{40} # v?[0-9]"],
        input="  - uses: actions/checkout@0123456789abcdef0123456789abcdef01234567 # v7.0.1\n",
        text=True,
        capture_output=True,
        cwd=ROOT,
    )
    print(f"B3 pin stdout={pinned.stdout.strip()!r} exit={pinned.returncode}")
    stancl = subprocess.run(checks[0], text=True, capture_output=True, cwd=ROOT)
    print(f"B3 stancl stdout={stancl.stdout.strip()!r} exit={stancl.returncode}")
    empty = subprocess.run(
        ["grep", "-nE", r"'8\.3'|Boxed profile"],
        input="php: ['8.4', '8.5']\n",
        text=True,
        capture_output=True,
    )
    print(f"B3 empty-8.3 stdout={empty.stdout.strip()!r} exit={empty.returncode}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
