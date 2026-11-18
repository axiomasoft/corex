#!/usr/bin/env python3
"""Apply the reviewed ownership integration patch to the pinned development tool."""

import hashlib
import json
import subprocess
from pathlib import Path


def main() -> None:
    root = Path(__file__).resolve().parents[1]
    spec = json.loads((root / "scripts/patches/packman-devtools.json").read_text())
    target = root / spec["file"]
    if not target.exists():
        return  # Production installations do not install devtools.
    lock = json.loads((root / "composer.lock").read_text())
    package = next(p for p in lock["packages-dev"] if p["name"] == "axiomasoft/packman-devtools")
    if package["source"]["reference"] != spec["source_reference"]:
        raise SystemExit("Packman devtools changed: review the ownership patch before upgrading.")
    digest = hashlib.sha256(target.read_bytes()).hexdigest()
    if digest == spec["after_sha256"]:
        return
    if digest != spec["before_sha256"]:
        raise SystemExit("Unrecognized Packman table-write gate; refusing to overwrite it.")
    subprocess.run(["patch", "--batch", "--forward", "-p1", "-i", spec["patch"]], cwd=root, check=True)
    if hashlib.sha256(target.read_bytes()).hexdigest() != spec["after_sha256"]:
        raise SystemExit("Packman ownership patch did not produce the reviewed source.")


if __name__ == "__main__":
    main()
