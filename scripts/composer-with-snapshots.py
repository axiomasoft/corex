#!/usr/bin/env python3
"""Run standalone package Composer commands with the monorepo's reviewed repositories.

Generated manifests/locks stay beside the source manifest so relative autoload and
vendor paths retain Composer's normal package-root semantics. Source files stay intact.
"""
from __future__ import annotations

import argparse
import importlib.util
import json
import os
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
GENERATED = ".composer-snapshots.json"


def audit_module():
    path = Path(__file__).resolve().parent / "distribution/audit-roots.py"
    spec = importlib.util.spec_from_file_location("snapshot_source_audit", path)
    assert spec and spec.loader
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def prepare(root: Path, directory: Path) -> Path:
    root, directory = root.resolve(), directory.resolve()
    if directory == root or root not in directory.parents:
        raise ValueError("working directory must be a source package inside the monorepo")
    source = directory / "composer.json"
    audit = audit_module()
    manifest = audit.read_json(source)
    audit.require_secure(manifest, str(source.relative_to(root)))
    effective, _ = audit.source_manifest(root, source, manifest)
    generated = directory / GENERATED
    # Refuse a symlink: generated metadata must never overwrite another source file.
    if generated.is_symlink() or generated.with_suffix(".lock").is_symlink():
        raise ValueError("snapshot manifest and lock must be ordinary local files")
    generated.write_text(json.dumps(effective, indent=4) + "\n", encoding="utf-8")
    return generated


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--working-dir", required=True, type=Path)
    parser.add_argument("command", nargs=argparse.REMAINDER, help="Composer command and arguments")
    args = parser.parse_args(argv)
    if not args.command:
        parser.error("a Composer command is required")
    command = args.command[1:] if args.command[0] == "--" else args.command
    if not command or any(arg == "-d" or arg == "--working-dir" or arg.startswith("--working-dir=") for arg in command):
        parser.error("pass the working directory to this wrapper only")
    try:
        generated = prepare(ROOT, args.working_dir)
        if command[0] == "update":
            command = [*command, *audit_module().reviewed_toolchain_arguments(ROOT, json.loads(generated.read_text(encoding="utf-8")))]
    except (OSError, RuntimeError, ValueError, TypeError) as error:
        parser.exit(2, f"composer snapshots: {error}\n")
    environment = {**os.environ, "COMPOSER": generated.name}
    return subprocess.run(["composer", *command], cwd=generated.parent, env=environment, check=False).returncode


if __name__ == "__main__":
    raise SystemExit(main())
