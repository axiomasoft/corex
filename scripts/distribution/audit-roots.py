#!/usr/bin/env python3
"""Audit every maintained Composer source root, retaining actual command evidence.

Release-only fixtures are inventoried but do not turn a source audit into release
qualification. A lock or installed vendor tree makes such a fixture auditable.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
EXCLUDED = {".git", "vendor", "node_modules", "build", "plans", "artifacts", "delivery", "dist", "__pycache__", ".pytest_cache", ".phpunit.cache", "audits"}
# Codex appends execution events during tool calls; these are not source inputs.
EXECUTION_STATE = {".codex/state"}
# Exact release consumers, with the source which defines their dist-only install.
RELEASE_FIXTURES = {
    "tests/Distribution/fixtures/public-consumer/composer.json": (
        "tests/Distribution/fixtures/public-consumer/README.md", "sealed ZIP dist artifacts"),
    "fixtures/consumers/equipment-registry/composer.json": (
        "scripts/distribution/verify_equipment_consumer.py", "extensions.materialize_repository(built, repository)"),
    **{f"fixtures/extensions/{name}/composer.json": (
        "scripts/distribution/verify_extension_fixtures.py", "materialize_repository(candidate, repository)")
        for name in ("custom-attribute-source", "custom-panel-provider", "custom-search-provider", "custom-transfer-codec", "custom-workflow-activity")},
}
HASH_KEYS = ("name", "version", "require", "require-dev", "conflict", "replace", "provide", "minimum-stability", "prefer-stable", "repositories", "extra")


class AuditError(RuntimeError):
    """An omitted root, stale binding or unsuccessful audit is RED."""


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def write_json(path: Path, value: object) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True) + "\n", encoding="utf-8")


def read_json(path: Path) -> dict:
    value = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(value, dict):
        raise AuditError(f"expected JSON object: {path}")
    return value


def content_hash(manifest: dict) -> str:
    # Composer Package\Locker::getContentHash: top-level ksort, JsonFile::encode(..., 0).
    relevant = {key: manifest[key] for key in HASH_KEYS if key in manifest}
    if "platform" in manifest.get("config", {}):
        relevant["config"] = {"platform": manifest["config"]["platform"]}
    def php_arrays(value):
        # Composer parses associative arrays: an empty JSON object re-encodes as [].
        if isinstance(value, dict):
            return {key: php_arrays(item) for key, item in value.items()} if value else []
        if isinstance(value, list):
            return [php_arrays(item) for item in value]
        return value

    payload = json.dumps(php_arrays(dict(sorted(relevant.items()))), ensure_ascii=True, separators=(",", ":")).replace("/", "\\/")
    return hashlib.md5(payload.encode("utf-8")).hexdigest()


def require_lock(manifest: Path, lock: Path) -> None:
    if not lock.is_file():
        raise AuditError(f"missing lock: {manifest}")
    data = read_json(lock)
    if not isinstance(data.get("packages"), list) or not isinstance(data.get("packages-dev", []), list):
        raise AuditError(f"invalid locked package inventory: {lock}")
    if data.get("content-hash") != content_hash(read_json(manifest)):
        raise AuditError(f"manifest/lock content-hash mismatch: {manifest}")


def require_secure(manifest: dict, label: str) -> None:
    config = manifest.get("config", {})
    if not isinstance(config, dict):
        raise AuditError(f"invalid Composer configuration: {label}")
    audit = config.get("audit", {})
    if not isinstance(audit, dict):
        raise AuditError(f"invalid audit configuration: {label}")
    if any(value for key, value in audit.items() if key.startswith("ignore")) or audit.get("abandoned") in ("ignore", "report"):
        raise AuditError(f"insecure audit bypass: {label}")
    if audit.get("block-insecure") is False or config.get("lock") is False:
        raise AuditError(f"insecure lock/audit bypass: {label}")
    if config.get("secure-http") is False or config.get("disable-tls") is True:
        raise AuditError(f"insecure transport bypass: {label}")
    policy_config = config.get("policy", {})
    if not isinstance(policy_config, dict) or not isinstance(policy_config.get("advisories", {}), dict):
        raise AuditError(f"invalid advisory policy: {label}")
    policy = policy_config.get("advisories", {})
    if policy.get("ignore") or policy.get("block") is False or policy.get("audit") is False:
        raise AuditError(f"insecure advisory policy: {label}")
    repositories = manifest.get("repositories", [])
    if not isinstance(repositories, (dict, list)):
        raise AuditError(f"invalid Composer repositories: {label}")
    values = repositories.values() if isinstance(repositories, dict) else repositories
    for repository in values:
        if isinstance(repository, dict):
            url = repository.get("url", "")
            if not isinstance(url, str):
                raise AuditError(f"invalid repository URL: {label}")
            if url.startswith("http://"):
                raise AuditError(f"insecure repository transport: {label}")
    for section in ("require", "require-dev"):
        if not isinstance(manifest.get(section, {}), dict):
            raise AuditError(f"invalid Composer requirements: {label}")


def require_environment(environment: dict[str, str]) -> None:
    for name in ("COMPOSER_NO_AUDIT", "COMPOSER_DISABLE_NETWORK", "COMPOSER_DISABLE_TLS"):
        if environment.get(name, "").lower() not in ("", "0", "false"):
            raise AuditError(f"insecure/missing audit environment: {name}")
    if environment.get("COMPOSER_AUDIT_ABANDONED", "fail") != "fail":
        raise AuditError("insecure audit environment: COMPOSER_AUDIT_ABANDONED")
    if environment.get("COMPOSER"):
        raise AuditError("alternate Composer manifest environment: COMPOSER")


def excluded_directory(root: Path, path: Path) -> bool:
    return path.name in EXCLUDED or str(path.relative_to(root)) in EXECUTION_STATE


def source_files(root: Path) -> list[Path]:
    paths = []
    for directory, dirs, files in os.walk(root, followlinks=False):
        dirs[:] = sorted(d for d in dirs if not excluded_directory(root, Path(directory) / d) and not (Path(directory) / d).is_symlink())
        paths.extend(Path(directory) / name for name in sorted(files) if not (Path(directory) / name).is_symlink())
    return paths


def tracked_manifests(root: Path) -> set[str]:
    if not (root / ".git").exists():
        return set()
    completed = subprocess.run(["git", "ls-files", "-z", "--", "composer.json", "**/composer.json"], cwd=root, capture_output=True, check=False)
    if completed.returncode:
        raise AuditError("cannot inventory tracked Composer roots")
    return {name for name in completed.stdout.decode("utf-8").split("\0") if name and not EXCLUDED.intersection(Path(name).parts)}


def inventory(root: Path) -> list[dict]:
    names = {str(path.relative_to(root)) for path in source_files(root) if path.name == "composer.json"}
    for directory, dirs, files in os.walk(root, followlinks=False):
        for name in dirs:
            candidate = Path(directory) / name
            if not excluded_directory(root, candidate) and candidate.is_symlink() and (candidate / "composer.json").is_file():
                raise AuditError(f"symlink source root directory: {candidate.relative_to(root)}")
        dirs[:] = [name for name in dirs if not excluded_directory(root, Path(directory) / name) and not (Path(directory) / name).is_symlink()]
        if "composer.json" in files:
            names.add(str((Path(directory) / "composer.json").relative_to(root)))
    names.update(tracked_manifests(root))
    names.add("composer.json")
    for directory in ("packages", "products"):
        if not (root / directory).is_dir():
            continue
        for package in (root / directory).iterdir():
            if package.is_dir() and (package / "src").is_dir() and source_files(package / "src"):
                names.add(str((package / "composer.json").relative_to(root)))
    rows = []
    for name in sorted(names):
        path = root / name
        if not path.is_file() or path.is_symlink():
            raise AuditError(f"missing source root manifest: {name}")
        manifest = read_json(path)
        row = {"manifest": name, "name": manifest.get("name"), "manifest_sha256": digest(path), "classification": "source"}
        if name in RELEASE_FIXTURES and not (path.parent / "composer.lock").exists() and not (path.parent / "vendor").exists():
            owner, anchor = RELEASE_FIXTURES[name]
            owner_path = root / owner
            if not owner_path.is_file() or anchor not in owner_path.read_text(encoding="utf-8"):
                raise AuditError(f"stale release-only classification: {name}")
            row.update({"classification": "release-only", "reason": "D125: dist candidate consumer; no source install/lock", "owner": owner, "owner_sha256": digest(owner_path), "anchor": anchor})
        rows.append(row)
    return rows


def snapshot(root: Path) -> dict:
    files = {str(path.relative_to(root)): digest(path) for path in source_files(root)}
    encoded = json.dumps(files, sort_keys=True, separators=(",", ":")).encode("utf-8")
    return {"sha256": hashlib.sha256(encoded).hexdigest(), "files": files}


def process(command: list[str], cwd: Path, environment: dict[str, str], timeout: int) -> subprocess.CompletedProcess[str]:
    return subprocess.run(command, cwd=cwd, env=environment, timeout=timeout, text=True, capture_output=True, check=False)


def run_command(role: str, arguments: list[str], cwd: Path, environment: dict[str, str], evidence: Path, runner, timeout: int) -> dict:
    command = ["composer", "--no-interaction", "--no-plugins", "--no-scripts", *arguments]
    try:
        completed = runner(command, cwd, environment, timeout)
        result = {"command": command, "cwd": str(cwd), "exit": completed.returncode, "stdout": completed.stdout, "stderr": completed.stderr}
    except (OSError, subprocess.TimeoutExpired) as error:
        result = {"command": command, "cwd": str(cwd), "exit": 124 if isinstance(error, subprocess.TimeoutExpired) else 127, "stdout": "", "stderr": str(error)}
    write_json(evidence / f"{role}.json", result)
    return {"role": role, "output": f"{role}.json", "sha256": digest(evidence / f"{role}.json"), "exit": result["exit"]}


def inherited_repositories(root: Path, manifest_path: Path, manifest: dict) -> tuple[list, list]:
    repositories, bindings = [], []
    parents = [manifest_path.parent, *manifest_path.parent.parents]
    for directory in parents:
        if directory != root and root not in directory.parents:
            break
        owner = directory / "composer.json"
        if not owner.is_file():
            continue
        parent = manifest if owner == manifest_path else read_json(owner)
        require_secure(parent, str(owner.relative_to(root)))
        declared = parent.get("repositories", [])
        values = declared.values() if isinstance(declared, dict) else declared
        for repository in values:
            copied = dict(repository) if isinstance(repository, dict) else repository
            if isinstance(copied, dict) and copied.get("type") == "path":
                url = copied.get("url")
                if not isinstance(url, str) or "://" in url:
                    raise AuditError(f"unsupported inherited path repository: {owner}")
                absolute = str(directory / url) if not Path(url).is_absolute() else url
                copied["url"] = absolute
                matches = sorted(Path(directory).glob(url)) if not Path(url).is_absolute() else sorted(Path('/').glob(url.lstrip('/')))
                if not matches:
                    raise AuditError(f"missing inherited path repository: {owner}: {url}")
                for match in matches:
                    resolved = match.resolve()
                    if root != resolved and root not in resolved.parents:
                        raise AuditError(f"inherited repository outside source: {url}")
                    dependency = resolved / "composer.json"
                    if not dependency.is_file():
                        continue
                    bindings.append({"manifest": str(dependency.relative_to(root)), "sha256": digest(dependency), "owner": str(owner.relative_to(root)), "url": url})
                # Reading source metadata is sufficient: no installation or symlink creation.
                copied["options"] = {**copied.get("options", {}), "symlink": False}
            if copied not in repositories:
                repositories.append(copied)
        if directory == root:
            break
    return repositories, bindings


def require_stable_harness(manifest: dict, bindings: list, root: Path, label: str) -> None:
    if manifest.get("minimum-stability", "stable") != "stable":
        raise AuditError(f"unstable source harness constraints: {label}")
    local_names = {read_json(root / binding["manifest"]).get("name") for binding in bindings}
    for section in ("require", "require-dev"):
        requirements = manifest.get(section, {})
        for name, constraint in requirements.items():
            if name not in local_names and re.search(r"(?:dev|alpha|beta|\brc\d*)", str(constraint), re.IGNORECASE):
                raise AuditError(f"unstable external harness dependency: {label}: {name}")


def audit_root(root: Path, row: dict, directory: Path, environment: dict[str, str], runner, timeout: int) -> dict:
    path = root / row["manifest"]
    manifest = read_json(path)
    receipt = {**row, "commands": [], "status": "RED"}
    shutil.copyfile(path, directory / "source-composer.json")
    try:
        require_secure(manifest, row["manifest"])
        lock = path.with_name("composer.lock")
        if lock.is_file():
            require_lock(path, lock)
            receipt["lock_kind"] = "source"
            receipt["source_lock_sha256"] = digest(lock)
            shutil.copyfile(lock, directory / "composer.lock")
            shutil.copyfile(path, directory / "composer.json")
            working = path.parent
            roles = [("validate", ["validate", "--strict"]), ("audit", ["audit", "--locked", "--format=json", "--abandoned=fail"])]
            for role, arguments in roles:
                receipt["commands"].append(run_command(role, arguments, working, environment, directory, runner, timeout))
        else:
            receipt["lock_kind"] = "isolated-solve"
            receipt["commands"].append(run_command("validate-source", ["validate", "--strict"], path.parent, environment, directory, runner, timeout))
            if receipt["commands"][-1]["exit"]:
                raise AuditError(f"validate failed: {row['manifest']}")
            with tempfile.TemporaryDirectory(prefix="corex-source-audit-") as temporary:
                working = Path(temporary)
                effective = dict(manifest)
                effective["repositories"], receipt["repository_bindings"] = inherited_repositories(root, path, manifest)
                require_stable_harness(effective, receipt["repository_bindings"], root, row["manifest"])
                write_json(working / "composer.json", effective)
                shutil.copyfile(working / "composer.json", directory / "composer.json")
                receipt["commands"].append(run_command("solve", ["update", "--no-install", "--no-progress", "--prefer-dist"], working, environment, directory, runner, timeout))
                if receipt["commands"][-1]["exit"]:
                    raise AuditError(f"bounded solve failed: {row['manifest']}")
                require_lock(working / "composer.json", working / "composer.lock")
                shutil.copyfile(working / "composer.lock", directory / "composer.lock")
                for role, arguments in (("validate", ["validate", "--strict"]), ("audit", ["audit", "--locked", "--format=json", "--abandoned=fail"])):
                    receipt["commands"].append(run_command(role, arguments, working, environment, directory, runner, timeout))
        receipt["effective_manifest_sha256"] = digest(directory / "composer.json")
        receipt["lock_sha256"] = digest(directory / "composer.lock")
        verify_commands(receipt, directory)
        receipt["status"] = "GREEN"
    except (AuditError, OSError, ValueError, TypeError) as error:
        receipt["error"] = str(error)
    write_json(directory / "receipt.json", receipt)
    return receipt


def verify_commands(receipt: dict, directory: Path) -> None:
    commands = receipt.get("commands", [])
    roles = [row.get("role") for row in commands]
    required = ["validate", "audit"] if receipt.get("lock_kind") == "source" else ["validate-source", "solve", "validate", "audit"]
    if roles != required:
        raise AuditError(f"missing/duplicate audit or validation: {receipt['manifest']}")
    for row in commands:
        output = directory / row["output"]
        if not output.is_file() or digest(output) != row["sha256"]:
            raise AuditError(f"missing/changed command evidence: {receipt['manifest']}: {row['role']}")
        result = read_json(output)
        if result.get("exit") != 0 or row.get("exit") != 0:
            raise AuditError(f"{row['role']} failed: {receipt['manifest']}: exit {result.get('exit')}")
        command = result.get("command", [])
        if command[:4] != ["composer", "--no-interaction", "--no-plugins", "--no-scripts"]:
            raise AuditError(f"unsafe/missing tool command: {receipt['manifest']}")
        expected = {"validate": ["validate", "--strict"], "validate-source": ["validate", "--strict"], "solve": ["update", "--no-install", "--no-progress", "--prefer-dist"], "audit": ["audit", "--locked", "--format=json", "--abandoned=fail"]}[row["role"]]
        if command[4:] != expected:
            raise AuditError(f"insecure/missing {row['role']} command: {receipt['manifest']}")
        if row["role"] == "audit":
            try:
                payload = json.loads(result["stdout"])
            except (KeyError, ValueError) as error:
                raise AuditError(f"missing audit JSON: {receipt['manifest']}") from error
            if not isinstance(payload, dict) or "advisories" not in payload or "abandoned" not in payload:
                raise AuditError(f"incomplete audit output: {receipt['manifest']}")
            if any(not isinstance(payload[key], (dict, list)) for key in ("advisories", "abandoned")):
                raise AuditError(f"invalid audit output: {receipt['manifest']}")
            if any(payload.get(name) for name in ("advisories", "abandoned", "ignored-advisories")):
                raise AuditError(f"insecure/abandoned or ignored audit findings: {receipt['manifest']}")


def verify_evidence(root: Path, evidence: Path) -> None:
    index = read_json(evidence / "index.json")
    current = inventory(root)
    if index.get("inventory") != current:
        raise AuditError("missing or changed roots in audit inventory")
    if index.get("source_sha256") != snapshot(root)["sha256"]:
        raise AuditError("source changed since audit evidence")
    if digest(evidence / "source.json") != index.get("source_inventory_sha256"):
        raise AuditError("source inventory evidence changed")
    version = index.get("composer_version", {})
    version_path = evidence / version.get("output", "missing-version.json")
    if not version_path.is_file() or digest(version_path) != version.get("sha256"):
        raise AuditError("missing/changed Composer version evidence")
    tool = read_json(version_path)
    if tool.get("exit") != 0 or tool.get("command") != ["composer", "--no-interaction", "--no-plugins", "--no-scripts", "--version"] or not re.match(r"^Composer version 2\.", tool.get("stdout", "")):
        raise AuditError("Composer 2 tool evidence missing/failed")
    expected = {row["manifest"] for row in current if row["classification"] == "source"}
    receipts = index.get("roots", [])
    if len(receipts) != len(expected) or {row["manifest"] for row in receipts} != expected:
        raise AuditError("missing root audit evidence")
    for row in receipts:
        directory = evidence / row["directory"]
        if digest(directory / "receipt.json") != row["sha256"]:
            raise AuditError(f"changed root audit receipt: {row['manifest']}")
        receipt = read_json(directory / "receipt.json")
        if receipt.get("status") != "GREEN":
            raise AuditError(f"root audit RED: {row['manifest']}: {receipt.get('error')}")
        if digest(root / receipt["manifest"]) != receipt["manifest_sha256"]:
            raise AuditError(f"source manifest changed: {row['manifest']}")
        if digest(directory / "source-composer.json") != receipt["manifest_sha256"]:
            raise AuditError(f"source manifest evidence changed: {row['manifest']}")
        original = read_json(directory / "source-composer.json")
        effective = read_json(directory / "composer.json")
        require_secure(original, row['manifest'])
        require_secure(effective, row['manifest'])
        if receipt["lock_kind"] == "source":
            if effective != original:
                raise AuditError(f"source manifest substitution: {row['manifest']}")
        else:
            repositories, bindings = inherited_repositories(root, root / receipt["manifest"], original)
            if effective != {**original, "repositories": repositories} or bindings != receipt.get("repository_bindings"):
                raise AuditError(f"isolated manifest/repository substitution: {row['manifest']}")
            require_stable_harness(effective, bindings, root, row['manifest'])
        if receipt["lock_kind"] == "source" and digest((root / receipt["manifest"]).with_name("composer.lock")) != receipt["source_lock_sha256"]:
            raise AuditError(f"source lock changed: {row['manifest']}")
        if digest(directory / "composer.json") != receipt["effective_manifest_sha256"] or digest(directory / "composer.lock") != receipt["lock_sha256"]:
            raise AuditError(f"lock/manifest evidence changed: {row['manifest']}")
        require_lock(directory / "composer.json", directory / "composer.lock")
        verify_commands(receipt, directory)


def audit(root: Path, evidence: Path, *, runner=process, timeout: int = 180, environment: dict[str, str] | None = None) -> dict:
    environment = dict(os.environ if environment is None else environment)
    evidence.mkdir(parents=True, exist_ok=True)
    index = {"schema": "corex-source-audits/1", "scope": "source", "status": "RED", "roots": [], "errors": []}
    try:
        if root == evidence or evidence in root.parents:
            raise AuditError("evidence cannot contain the source root")
        if root in evidence.parents and not EXCLUDED.intersection(evidence.relative_to(root).parts):
            raise AuditError("in-tree evidence must be in an excluded build directory")
        require_environment(environment)
        rows = inventory(root)
        source = snapshot(root)
        write_json(evidence / "source.json", source)
        index.update({"inventory": rows, "source_sha256": source["sha256"], "source_inventory_sha256": digest(evidence / "source.json")})
        with tempfile.TemporaryDirectory(prefix="corex-audit-home-") as temporary:
            environment.update({"COMPOSER_HOME": temporary, "COMPOSER_CACHE_DIR": str(Path(temporary) / "cache"), "COMPOSER_NO_INTERACTION": "1", "COMPOSER_AUDIT_ABANDONED": "fail"})
            index["composer_version"] = run_command("composer-version", ["--version"], root, environment, evidence, runner, timeout)
            for number, row in enumerate(rows):
                if row["classification"] != "source":
                    continue
                name = f"{number:03d}-" + re.sub(r"[^A-Za-z0-9_.-]", "-", row["manifest"])
                directory = evidence / name
                directory.mkdir(parents=True, exist_ok=True)
                receipt = audit_root(root, row, directory, environment, runner, timeout)
                index["roots"].append({"manifest": row["manifest"], "directory": name, "sha256": digest(directory / "receipt.json")})
                if receipt["status"] != "GREEN":
                    index["errors"].append(f"{row['manifest']}: {receipt.get('error')}")
        write_json(evidence / "index.json", index)
        verify_evidence(root, evidence)
        index["status"] = "GREEN"
    except (AuditError, OSError, ValueError, TypeError) as error:
        if str(error) not in index["errors"]:
            index["errors"].append(str(error))
    write_json(evidence / "index.json", index)
    return index


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--scope", choices=("source",), required=True)
    parser.add_argument("--evidence-dir", type=Path, required=True)
    parser.add_argument("--source-root", type=Path, default=ROOT)
    parser.add_argument("--timeout", type=int, default=180)
    parser.add_argument("--verify", action="store_true", help="verify retained evidence without network commands")
    args = parser.parse_args()
    if args.timeout < 1 or args.timeout > 1200:
        parser.error("timeout must be between 1 and 1200 seconds")
    if args.verify:
        try:
            verify_evidence(args.source_root.resolve(), args.evidence_dir.resolve())
            print("Composer source audit evidence GREEN")
            return 0
        except (AuditError, OSError, ValueError, TypeError) as error:
            print(f"Composer source audit evidence RED: {error}", file=sys.stderr)
            return 1
    receipt = audit(args.source_root.resolve(), args.evidence_dir.resolve(), timeout=args.timeout)
    print(json.dumps({"status": receipt["status"], "roots": len(receipt["roots"]), "errors": receipt["errors"], "evidence": str(args.evidence_dir)}))
    return 0 if receipt["status"] == "GREEN" else 1


if __name__ == "__main__":
    raise SystemExit(main())
