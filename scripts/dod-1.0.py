#!/usr/bin/env python3
"""Normative DoD 1.0 manifest, renderer and fail-closed gate.

P17.9 owns the 27-row manifest and generated document.
P17.10 owns execution, freshness and planted-negative proof.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import subprocess
import sys
from pathlib import Path

SCHEMA_VERSION = "corex-dod/1.0"
RESULT_VERSION = "corex-dod-result/1"
MAP_VERSION = "corex-dod-map-validation/1"
MODES = frozenset({"live-command", "candidate-receipt"})
STATUSES = ("green", "red", "unknown", "skipped", "stale", "not-run")
FAIL_CLOSED = frozenset({"red", "unknown", "skipped", "stale", "not-run"})
ID_RE = re.compile(r"^DOD-(\d{2})$")
OWNER_RE = re.compile(r"^P\d+\.\d+$")
EXPECTED_IDS = tuple(f"DOD-{index:02d}" for index in range(1, 28))
PLAN_DIR = "plans/2026.09.19-№1-COREX-PLATFORM-HARDENING"
CANDIDATE_RECEIPT = f"{PLAN_DIR}/findings/P17-candidate-receipt.json"
PROFILES = {
    "bare-single",
    "modular-single",
    "modular-multi",
    "worker-only",
    "search-portable",
    "full-corex",
}
CI_IDS = frozenset({
    "DOD-01", "DOD-02", "DOD-03", "DOD-04", "DOD-05", "DOD-06", "DOD-07",
    "DOD-08", "DOD-10", "DOD-11", "DOD-14", "DOD-15", "DOD-16", "DOD-17",
    "DOD-18", "DOD-19", "DOD-20", "DOD-21", "DOD-23", "DOD-26", "DOD-27",
})
SKIP_MARKERS = ("plan-close deferred", "bc-check: skip", "undeclared skip")
LIVE_TIMEOUT = {
    "DOD-22": 1200,
    "DOD-09": 240,
    "DOD-12": 240,
    "DOD-13": 240,
}
DEFAULT_TIMEOUT = 180
OUTPUT_LIMIT = 64_000


class DodError(Exception):
    def __init__(self, diagnostic: str) -> None:
        super().__init__(diagnostic)
        self.diagnostic = diagnostic


def repo_root() -> Path:
    return Path(__file__).resolve().parent.parent


def sha256_bytes(payload: bytes) -> str:
    return hashlib.sha256(payload).hexdigest()


def sha256_file(path: Path) -> str:
    return sha256_bytes(path.read_bytes())


def load_json(path: Path) -> object:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise DodError(f"unparseable JSON: {path}: {exc}") from exc


def canonical_dumps(payload: object) -> str:
    return json.dumps(payload, ensure_ascii=False, indent=2) + "\n"


def _phase_of(owner: str) -> str:
    return owner.split(".", 1)[0]


def _reject_escape(value: str) -> None:
    path = Path(value)
    if path.is_absolute() or ".." in path.parts:
        raise DodError(f"path outside repository: {value}")


def validate_manifest(document: object, root: Path) -> dict:
    if not isinstance(document, dict):
        raise DodError("manifest must be an object")
    if document.get("schema_version") != SCHEMA_VERSION:
        raise DodError("unsupported schema_version")
    provenance = document.get("provenance")
    if not isinstance(provenance, dict):
        raise DodError("provenance must be an object")
    if provenance.get("audit_assertions") != 25 or provenance.get("plan_assertions") != 2:
        raise DodError("provenance must record 25 audit assertions plus 2 plan assertions")
    if provenance.get("decision") != "D95":
        raise DodError("provenance decision must be D95")
    rows = document.get("assertions")
    if not isinstance(rows, list):
        raise DodError("assertions must be a list")
    if len(rows) != 27:
        raise DodError(f"expected 27 assertions, found {len(rows)}")
    seen: list[str] = []
    for row in rows:
        if not isinstance(row, dict):
            raise DodError("assertion must be an object")
        unknown = set(row) - {
            "id", "statement", "owner", "mode", "command", "receipt",
            "freshness_inputs", "negative_fixture",
        }
        if unknown:
            raise DodError(f"unsupported assertion fields: {sorted(unknown)}")
        identifier = row.get("id")
        if not isinstance(identifier, str) or ID_RE.fullmatch(identifier) is None:
            raise DodError(f"invalid assertion id: {identifier!r}")
        if identifier in seen:
            raise DodError(f"duplicate ID: {identifier}")
        seen.append(identifier)
        statement = row.get("statement")
        if not isinstance(statement, str) or not statement.strip():
            raise DodError(f"{identifier}: statement is empty")
        owners = row.get("owner")
        if not isinstance(owners, list) or not owners:
            raise DodError(f"{identifier}: ownerless row")
        for owner in owners:
            if not isinstance(owner, str) or OWNER_RE.fullmatch(owner) is None:
                raise DodError(f"{identifier}: unqualified plan owner: {owner!r}")
            phase = _phase_of(owner)
            spec = root / PLAN_DIR / "phases" / phase / f"{owner}.md"
            if not spec.is_file():
                raise DodError(f"{identifier}: unresolved owner {owner}")
        mode = row.get("mode")
        if mode not in MODES:
            raise DodError(f"{identifier}: unsupported mode: {mode!r}")
        has_command = "command" in row
        has_receipt = "receipt" in row
        if has_command and has_receipt:
            raise DodError(f"{identifier}: both evidence modes")
        if mode == "live-command":
            if not has_command or has_receipt or "freshness_inputs" in row:
                raise DodError(f"{identifier}: live-command requires only command")
            command = row["command"]
            if not isinstance(command, list) or not command or not all(
                isinstance(part, str) and part for part in command
            ):
                raise DodError(f"{identifier}: command must be a nonempty argv array")
            if any(token in part for part in command for token in ("|", ";", "&", ">", "<", "`", "\n")):
                raise DodError(f"{identifier}: command contains shell syntax")
            script = command[1] if command[0] in {"php", "python3", "bash"} else command[0]
            _reject_escape(script)
            if not (root / script).is_file():
                raise DodError(f"{identifier}: absent command target: {script}")
        else:
            if not has_receipt or has_command:
                raise DodError(f"{identifier}: candidate-receipt requires only receipt")
            receipt = row.get("receipt")
            if not isinstance(receipt, str) or not receipt:
                raise DodError(f"{identifier}: absent receipt target")
            _reject_escape(receipt)
            if not (root / receipt).is_file():
                raise DodError(f"{identifier}: absent receipt target: {receipt}")
            inputs = row.get("freshness_inputs")
            if not isinstance(inputs, list) or not inputs:
                raise DodError(f"{identifier}: receipt row requires freshness inputs")
            for item in inputs:
                if not isinstance(item, str) or not item:
                    raise DodError(f"{identifier}: invalid freshness input")
                _reject_escape(item)
                if not (root / item).exists():
                    raise DodError(f"{identifier}: absent freshness input: {item}")
        fixture = row.get("negative_fixture")
        if not isinstance(fixture, str) or not re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", fixture):
            raise DodError(f"{identifier}: negative fixture id is empty or invalid")
    if tuple(seen) != EXPECTED_IDS:
        raise DodError("IDs must be contiguous DOD-01 through DOD-27 in ID order")
    return document


def load_manifest(path: Path, root: Path | None = None) -> dict:
    root = root or repo_root()
    document = load_json(path)
    return validate_manifest(document, root)


def render_markdown(manifest: dict) -> str:
    lines = [
        "# Definition of Done 1.0",
        "",
        "Generated from `scripts/dod-1.0.json` by `python3 scripts/dod-1.0.py render`.",
        "Hand edits fail `render --check`.",
        "",
        "Provenance: 25 audit assertions (DOD-01–DOD-25) plus 2 plan assertions",
        "(DOD-26–DOD-27), decision D95. A score is not a pass. Exactly one evidence",
        "mode is active on each row.",
        "",
        "| ID | Assertion | Owner | Mode | Evidence | Negative fixture |",
        "|:--|:--|:--|:--|:--|:--|",
    ]
    for row in manifest["assertions"]:
        owners = ", ".join(f"`{owner}`" for owner in row["owner"])
        if row["mode"] == "live-command":
            evidence = "`" + " ".join(row["command"]) + "`"
        else:
            evidence = f"`{row['receipt']}`"
        statement = row["statement"].replace("|", "\\|")
        lines.append(
            f"| {row['id']} | {statement} | {owners} | `{row['mode']}` | {evidence} | `{row['negative_fixture']}` |"
        )
    lines.extend([
        "",
        "Receipt rows bind source commit, candidate digest, environment, command exit",
        "and freshness-input digests. `unknown`, `skipped`, `stale` and `not-run` stay red.",
        "",
    ])
    return "\n".join(lines)


def explain_text(manifest: dict, identifier: str) -> str:
    rows = [row for row in manifest["assertions"] if row["id"] == identifier]
    if len(rows) != 1:
        raise DodError(f"unknown assertion: {identifier}")
    row = rows[0]
    evidence = row["command"] if row["mode"] == "live-command" else row["receipt"]
    return "\n".join([
        f"id: {row['id']}",
        f"statement: {row['statement']}",
        f"owner: {', '.join(row['owner'])}",
        f"mode: {row['mode']}",
        f"evidence: {evidence}",
        f"negative_fixture: {row['negative_fixture']}",
        "",
    ])


def git_head(root: Path) -> str:
    completed = subprocess.run(
        ["git", "rev-parse", "HEAD"],
        cwd=root,
        check=False,
        capture_output=True,
        text=True,
    )
    commit = completed.stdout.strip()
    if completed.returncode != 0 or not re.fullmatch(r"[0-9a-f]{40}", commit):
        raise DodError("missing candidate SHA")
    return commit


def current_environment() -> dict[str, str]:
    environment = {"python": f"{sys.version_info.major}.{sys.version_info.minor}.{sys.version_info.micro}"}
    php = subprocess.run(["php", "-r", "echo PHP_VERSION;"], capture_output=True, text=True, check=False)
    if php.returncode == 0 and php.stdout.strip():
        environment["php"] = php.stdout.strip()
    composer = subprocess.run(["composer", "--version", "--no-ansi"], capture_output=True, text=True, check=False)
    match = re.search(r"(\d+\.\d+\.\d+)", composer.stdout)
    if composer.returncode == 0 and match:
        environment["composer"] = match.group(1)
    return environment


def candidate_binding(path: Path, root: Path) -> dict[str, str]:
    document = load_json(path)
    if not isinstance(document, dict):
        raise DodError("candidate JSON must be an object")
    source_commit = document.get("source_commit")
    if not isinstance(source_commit, str) or not re.fullmatch(r"[0-9a-f]{40}", source_commit):
        raise DodError("candidate JSON is missing source_commit")
    digest = document.get("candidate_sha256")
    if not isinstance(digest, str):
        digest = sha256_file(path)
    if not isinstance(digest, str) or not re.fullmatch(r"[0-9a-f]{64}", digest):
        digest = sha256_file(path)
    return {
        "source_commit": source_commit,
        "candidate_sha256": digest,
        "file_sha256": sha256_file(path),
    }


def _dod_mark(receipt: dict, identifier: str) -> str | None:
    dod = receipt.get("dod")
    if not isinstance(dod, dict) or identifier not in dod:
        return None
    return str(dod[identifier])


def _mark_status(mark: str) -> tuple[str, str]:
    lowered = mark.lower()
    if any(token in lowered for token in ("skip", "unknown", "not-run", "not run")):
        return "skipped" if "skip" in lowered else "unknown", mark
    if lowered.startswith("fail") or lowered.startswith("red"):
        return "red", mark
    if lowered == "pass" or lowered.startswith("pass"):
        return "green", mark
    return "red", f"unclassified: {mark}"


def _input_status(row: dict, receipt: dict, root: Path) -> tuple[str, str] | None:
    inputs = row.get("freshness_inputs") or []
    stored = receipt.get("inputs")
    if not isinstance(stored, dict):
        return None
    values = {str(value) for value in stored.values()}
    for relative in inputs:
        digest = sha256_file(root / relative)
        if digest not in values:
            return "stale", f"freshness input drift: {relative}"
    return None


def _binding_status(receipt: dict, binding: dict, head: str) -> tuple[str, str] | None:
    source = receipt.get("source_commit")
    if isinstance(source, str) and source not in {head, binding["source_commit"]}:
        return "stale", "stale receipt commit"
    digest = receipt.get("candidate_sha256")
    if isinstance(digest, str) and digest != binding["candidate_sha256"]:
        return "stale", "stale candidate digest"
    if not isinstance(source, str) and not isinstance(digest, str):
        return "stale", "missing source binding"
    return None


def _environment_status(receipt: dict, environment: dict[str, str]) -> tuple[str, str] | None:
    declared = receipt.get("environment")
    if not isinstance(declared, dict):
        return None
    for key, expected in declared.items():
        actual = environment.get(str(key))
        if not isinstance(expected, str) or actual is None:
            return "stale", f"environment mismatch: {key}"
        if not (actual == expected or actual.startswith(expected + ".") or expected.startswith(actual + ".")):
            return "stale", f"environment mismatch: {key}"
    return None


def _workspace_violation(value: object) -> bool:
    if value is True:
        return True
    if isinstance(value, str) and (value.startswith("/") or "vendor/autoload.php" in value):
        return True
    return False


def judge_receipt(row: dict, receipt: object, *, root: Path, binding: dict, head: str,
                  environment: dict[str, str]) -> tuple[str, str]:
    if not isinstance(receipt, dict):
        return "red", "unparseable receipt"
    identifier = row["id"]
    schema = str(receipt.get("schema_version") or "")
    if schema == "dod-fixture-receipt/1":
        return _judge_fixture(row, receipt, root=root, binding=binding, head=head, environment=environment)
    drifted = _input_status(row, receipt, root)
    if drifted:
        return drifted
    bound = _binding_status(receipt, binding, head)
    if bound:
        return bound
    mismatched = _environment_status(receipt, environment)
    if mismatched:
        return mismatched
    mark = _dod_mark(receipt, identifier)
    if mark is not None:
        status, diagnostic = _mark_status(mark)
        if status != "green":
            return status, diagnostic
    result = receipt.get("result")
    if result in {"skipped", "unknown", "not-run"}:
        return "skipped" if result == "skipped" else "unknown", str(result)
    if identifier == "DOD-17" and schema == "corex-d107-text/1":
        return _judge_d107(str(receipt.get("text") or ""))
    if schema == "corex-profile-receipt/1":
        return _judge_profile(identifier, receipt, mark)
    if schema == "corex-equipment-consumer-receipt/1":
        return _judge_consumer(identifier, receipt)
    if schema == "corex-extension-fixture-receipt/1":
        return _judge_extensions(receipt)
    if schema == "corex-upgrade-receipt/1":
        return _judge_upgrade(identifier, receipt, mark)
    if schema == "corex-scorecard-docs-receipt/1":
        if mark is None:
            return "red", "scorecard receipt has no DOD mark"
        return "green", mark
    if result not in {"PASS", "green"}:
        return "red", f"unclassified result: {result!r}"
    if mark is None and identifier not in {"DOD-08", "DOD-19", "DOD-20", "DOD-27"}:
        return "red", "vacuous receipt"
    return "green", "PASS"


def _judge_fixture(row: dict, receipt: dict, *, root: Path, binding: dict, head: str,
                   environment: dict[str, str]) -> tuple[str, str]:
    if receipt.get("result") in {"skipped", "unknown", "not-run"}:
        return "skipped" if receipt.get("result") == "skipped" else "unknown", str(receipt.get("result"))
    bound = _binding_status(receipt, binding, head)
    if bound:
        return bound
    mismatched = _environment_status(receipt, environment)
    if mismatched:
        return mismatched
    command = receipt.get("command")
    if isinstance(command, dict) and command.get("exit_code") not in (0, None):
        return "red", f"nonzero command exit: {command.get('exit_code')}"
    stored = receipt.get("input_sha256")
    if isinstance(stored, dict):
        for relative, digest in stored.items():
            _reject_escape(str(relative))
            if sha256_file(root / str(relative)) != digest:
                return "stale", f"freshness input drift: {relative}"
    if _workspace_violation(receipt.get("autoload_workspace")):
        return "red", "workspace autoload path"
    required = receipt.get("required_profiles")
    present = receipt.get("profiles")
    if isinstance(required, list):
        if not isinstance(present, list):
            return "red", "omitted profile"
        missing = [item for item in required if item not in present]
        if missing:
            return "red", f"omitted profile: {', '.join(map(str, missing))}"
    defect = receipt.get("defect")
    if isinstance(defect, str) and defect:
        return "red", defect
    if receipt.get("result") != "PASS":
        return "red", "unclassified result"
    return "green", "PASS"


def _judge_d107(text: str) -> tuple[str, str]:
    if "D107" not in text or "no-move" not in text:
        return "red", "missing D107 no-move receipt"
    if "attributes profile B + P10.8 span evidence | pass (D107)" not in text:
        return "red", "D107 profile B receipt is not pass"
    return "green", "D107 profile B no-move"


def _judge_profile(identifier: str, receipt: dict, mark: str | None) -> tuple[str, str]:
    matrix = receipt.get("matrix")
    if not isinstance(matrix, list) or not matrix:
        return "red", "profile matrix is empty"
    for cell in matrix:
        if not isinstance(cell, dict):
            return "red", "unparseable profile cell"
        status = cell.get("status")
        if status in {"skip", "skipped", "unknown", "not-run"}:
            return "skipped", f"undeclared skip: {cell.get('id')}"
        if status == "excluded":
            if not cell.get("reason"):
                return "red", f"excluded profile cell has no reason: {cell.get('id')}"
            continue
        if status != "passed":
            return "red", f"profile cell not passed: {cell.get('id')}"
        if _workspace_violation(cell.get("autoload_workspace")):
            return "red", "workspace autoload path"
    profiles = {cell.get("profile") for cell in matrix if isinstance(cell, dict)}
    if identifier == "DOD-26" and not PROFILES <= profiles:
        missing = sorted(PROFILES - profiles)
        return "red", f"omitted profile: {', '.join(missing)}"
    if identifier == "DOD-14" and "search-portable" not in profiles and "bare-single" not in profiles:
        return "red", "omitted profile: Meilisearch-optional"
    if identifier == "DOD-15":
        bare = [cell for cell in matrix if isinstance(cell, dict) and cell.get("profile") == "bare-single"]
        if not bare:
            return "red", "omitted profile: bare-single"
        for cell in bare:
            packages = cell.get("packages") or []
            if "corex/search-intl" in packages:
                return "red", "Search Intl is not removable from bare-single"
    if mark is not None:
        return _mark_status(mark)
    if receipt.get("result") != "PASS":
        return "red", "profile receipt is not PASS"
    return "green", "PASS"


def _judge_consumer(identifier: str, receipt: dict) -> tuple[str, str]:
    if receipt.get("result") != "PASS":
        return "red", "consumer receipt is not PASS"
    if receipt.get("vocabulary") != "clean":
        return "red", "consumer vocabulary is not clean"
    if receipt.get("flex_dependency") != "rejected":
        return "red", "Flex dependency was not rejected"
    if _workspace_violation(receipt.get("autoload_workspace")):
        return "red", "workspace autoload path"
    if identifier == "DOD-08":
        restart = receipt.get("worker_restart")
        if not isinstance(restart, dict) or restart.get("maintenance") != "completed":
            return "red", "worker restart evidence is incomplete"
    if identifier == "DOD-27" and not receipt.get("lower_level_packages"):
        return "red", "consumer has no lower-level package set"
    return "green", "PASS"


def _judge_extensions(receipt: dict) -> tuple[str, str]:
    if receipt.get("result") != "PASS":
        return "red", "extension receipt is not PASS"
    fixtures = receipt.get("fixtures")
    if not isinstance(fixtures, list) or len(fixtures) != 5:
        return "red", "extension matrix is not five fixtures"
    for fixture in fixtures:
        if not isinstance(fixture, dict) or fixture.get("contract") != "pass":
            return "red", "extension contract did not pass"
        if _workspace_violation(fixture.get("autoload_workspace")):
            return "red", "workspace autoload path"
    return "green", "PASS"


def _judge_upgrade(identifier: str, receipt: dict, mark: str | None) -> tuple[str, str]:
    if mark is not None:
        status, diagnostic = _mark_status(mark)
        if status != "green":
            return status, diagnostic
    elif receipt.get("result") != "PASS":
        return "red", "upgrade receipt is not PASS"
    packages = receipt.get("packages")
    if isinstance(packages, list):
        for package in packages:
            if isinstance(package, dict) and package.get("status") in {"skip", "skipped", "unknown", "not-run"}:
                return "skipped", f"upgrade skip: {package.get('name')}"
    if identifier == "DOD-22" and mark is None:
        return "red", "DOD-22 upgrade mark is missing"
    return "green", mark or "PASS"


def classify_bc_output(output: str) -> tuple[str, str]:
    if re.search(r"bc-check:\s+skip\b", output):
        return "skipped", "bc-check skip"
    if "missing candidate SHA" in output or re.search(r"candidate=\s*(\||$)", output):
        return "red", "missing candidate SHA"
    rows = re.findall(
        r"bc-check:\s+\S+\s+\([^)]+\)\s+\|(compatible|bc-break|infrastructure)\|\s+stability=(\S+)\s+base=(\S+)\s+candidate=(\S+)",
        output,
    )
    if not rows:
        return "red", "unclassified BC output"
    for classified, stability, _base, candidate in rows:
        if not re.fullmatch(r"[0-9a-f]{7,40}", candidate):
            return "red", "missing candidate SHA"
        if classified == "infrastructure":
            return "red", "infrastructure-error"
        if classified == "bc-break" and stability == "stable":
            return "red", "stable bc-break"
        if classified not in {"compatible", "bc-break"}:
            return "red", f"unclassified exit: {classified}"
    return "green", f"{len(rows)} classified BC rows"


def _bounded(completed: subprocess.CompletedProcess[str]) -> str:
    output = (completed.stdout or "") + (completed.stderr or "")
    if len(output) > OUTPUT_LIMIT:
        return output[:OUTPUT_LIMIT] + "\n[truncated]\n"
    return output


def run_command(argv: list[str], root: Path, timeout: int) -> tuple[int, str]:
    try:
        completed = subprocess.run(
            argv,
            cwd=root,
            check=False,
            capture_output=True,
            text=True,
            timeout=timeout,
            env=os.environ.copy(),
        )
    except subprocess.TimeoutExpired as exc:
        partial = ((exc.stdout or "") if isinstance(exc.stdout, str) else "") + (
            (exc.stderr or "") if isinstance(exc.stderr, str) else ""
        )
        return 124, partial[:OUTPUT_LIMIT] + "\n[timeout]\n"
    return completed.returncode, _bounded(completed)


def judge_live(row: dict, root: Path) -> tuple[str, str, int, str]:
    timeout = LIVE_TIMEOUT.get(row["id"], DEFAULT_TIMEOUT)
    code, output = run_command(row["command"], root, timeout)
    lowered = output.lower()
    if any(marker in lowered for marker in SKIP_MARKERS):
        return "skipped", "skip", code, output
    if row["id"] == "DOD-22" and any(part.endswith("bc-check.sh") for part in row["command"]):
        status, diagnostic = classify_bc_output(output)
        if code not in (0, 3) and status == "green":
            return "red", f"unclassified exit: {code}", code, output
        if code not in (0, 3) and status != "red":
            return "red", f"unclassified exit: {code}", code, output
        return status, diagnostic, code, output
    if code == 0:
        return "green", "exit 0", code, output
    diagnostic = "nonzero command"
    if row["id"] == "DOD-25" and "heading" in lowered:
        diagnostic = "README contract incomplete"
    elif "missing api symbol" in lowered or "outside extra.corex.public-api" in lowered:
        diagnostic = "missing API symbol"
    elif "internal" in lowered and ("import" in lowered or "forbidden" in lowered):
        diagnostic = "foreign Internal import"
    elif "workspace path" in lowered or "workspace autoload" in lowered:
        diagnostic = "workspace autoload path"
    elif "omitted profile" in lowered or "missing profile" in lowered:
        diagnostic = "omitted profile"
    return "red", diagnostic, code, output


def producer_phases(output: str) -> list[str]:
    try:
        document = json.loads(output)
    except json.JSONDecodeError:
        return []
    if not isinstance(document, dict):
        return []
    phases = []
    for violation in document.get("violations") or []:
        if isinstance(violation, dict) and isinstance(violation.get("producer_phase"), str):
            phase = violation["producer_phase"]
            if phase not in phases:
                phases.append(phase)
    return phases


def check_manifest(manifest: dict, *, root: Path, candidate: Path, profile: str,
                   head: str | None = None, environment: dict[str, str] | None = None) -> dict:
    if profile not in {"ci", "release"}:
        raise DodError(f"unsupported profile: {profile}")
    binding = candidate_binding(candidate, root)
    head = head or git_head(root)
    environment = environment or current_environment()
    selected = [
        row for row in manifest["assertions"]
        if profile == "release" or row["id"] in CI_IDS
    ]
    rows = []
    return_map: dict[str, list[str]] = {}
    for row in selected:
        if row["mode"] == "live-command":
            status, diagnostic, code, output = judge_live(row, root)
            record = {
                "id": row["id"],
                "status": status,
                "diagnostic": diagnostic,
                "mode": row["mode"],
                "exit_code": code,
                "command": row["command"],
            }
            phases = producer_phases(output)
        else:
            receipt_path = root / row["receipt"]
            try:
                if receipt_path.suffix == ".md":
                    receipt = {
                        "schema_version": "corex-d107-text/1",
                        "text": receipt_path.read_text(encoding="utf-8"),
                        "source_commit": head,
                        "candidate_sha256": binding["candidate_sha256"],
                    }
                else:
                    receipt = load_json(receipt_path)
            except (OSError, UnicodeError, DodError) as exc:
                status, diagnostic = "red", f"unparseable receipt: {exc}"
            else:
                status, diagnostic = judge_receipt(
                    row, receipt, root=root, binding=binding, head=head, environment=environment,
                )
            record = {
                "id": row["id"],
                "status": status,
                "diagnostic": diagnostic,
                "mode": row["mode"],
                "receipt": row["receipt"],
            }
            phases = [_phase_of(owner) for owner in row["owner"]]
        if record["status"] in FAIL_CLOSED:
            owners = phases or [_phase_of(owner) for owner in row["owner"]]
            return_map[row["id"]] = owners
            record["return_to"] = owners
        rows.append(record)
    if profile == "release" and len(rows) != 27:
        raise DodError(f"release result has {len(rows)} rows")
    summary = {status: sum(1 for row in rows if row["status"] == status) for status in STATUSES}
    summary["rows"] = len(rows)
    return {
        "schema_version": RESULT_VERSION,
        "profile": profile,
        "source_commit": head,
        "candidate_source_commit": binding["source_commit"],
        "candidate_sha256": binding["candidate_sha256"],
        "candidate_file_sha256": binding["file_sha256"],
        "environment": environment,
        "manifest_sha256": sha256_bytes(canonical_dumps(manifest).encode("utf-8")),
        "summary": summary,
        "rows": rows,
        "return_map": return_map,
    }


def result_is_green(result: dict) -> bool:
    summary = result["summary"]
    return summary["green"] == summary["rows"] and all(
        summary[status] == 0 for status in FAIL_CLOSED
    )


def map_validation(manifest: dict, *, render_stable: bool) -> dict:
    return {
        "schema_version": MAP_VERSION,
        "result": "PASS" if render_stable else "FAIL",
        "ids": len(manifest["assertions"]),
        "contiguous": [row["id"] for row in manifest["assertions"]] == list(EXPECTED_IDS),
        "owners_resolved": True,
        "paths_resolved": True,
        "render_byte_stable": render_stable,
        "provenance": manifest["provenance"],
    }


def reject_stale_ci(evidence_path: Path, head: str) -> None:
    document = load_json(evidence_path)
    if not isinstance(document, dict):
        raise DodError("stale CI evidence: unparseable")
    commit = document.get("source_commit")
    if commit != head:
        raise DodError(f"stale CI evidence: {commit} != {head}")
    if document.get("profile") != "ci":
        raise DodError("stale CI evidence: profile is not ci")
    if not result_is_green(document):
        raise DodError("stale CI evidence: CI subset is not green")


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="CoreX DoD 1.0 manifest and gate")
    parser.add_argument("--root", type=Path, default=None)
    sub = parser.add_subparsers(dest="action", required=True)

    render = sub.add_parser("render")
    render.add_argument("--manifest", type=Path, default=None)
    render.add_argument("--output", type=Path, default=None)
    render.add_argument("--check", action="store_true")

    explain = sub.add_parser("explain")
    explain.add_argument("identifier")
    explain.add_argument("--manifest", type=Path, default=None)

    check = sub.add_parser("check")
    check.add_argument("--manifest", type=Path, default=None)
    check.add_argument("--candidate", type=Path, required=True)
    check.add_argument("--profile", choices=("ci", "release"), default="release")
    check.add_argument("--output", type=Path, default=None)
    check.add_argument("--reject-stale-ci", type=Path, default=None)

    mapped = sub.add_parser("map")
    mapped.add_argument("--manifest", type=Path, default=None)
    mapped.add_argument("--output", type=Path, required=True)
    mapped.add_argument("--document", type=Path, default=None)

    args = parser.parse_args(argv)
    root = (args.root or repo_root()).resolve()
    manifest_path = (args.manifest or (root / "scripts/dod-1.0.json")).resolve()
    try:
        manifest = load_manifest(manifest_path, root)
        if args.action == "render":
            rendered = render_markdown(manifest)
            output = args.output or (root / "docs/architecture/dod-1.0.md")
            if args.check:
                current = output.read_text(encoding="utf-8") if output.is_file() else ""
                if current != rendered:
                    raise DodError("generated document drifted")
                return 0
            output.parent.mkdir(parents=True, exist_ok=True)
            output.write_text(rendered, encoding="utf-8")
            return 0
        if args.action == "explain":
            sys.stdout.write(explain_text(manifest, args.identifier))
            return 0
        if args.action == "map":
            document = args.document or (root / "docs/architecture/dod-1.0.md")
            rendered = render_markdown(manifest)
            stable = document.is_file() and document.read_text(encoding="utf-8") == rendered
            payload = map_validation(manifest, render_stable=stable)
            args.output.write_text(canonical_dumps(payload), encoding="utf-8")
            return 0 if stable else 1
        result = check_manifest(
            manifest, root=root, candidate=args.candidate.resolve(), profile=args.profile,
        )
        if args.reject_stale_ci is not None:
            reject_stale_ci(args.reject_stale_ci, result["source_commit"])
        text = canonical_dumps(result)
        if args.output is not None:
            args.output.parent.mkdir(parents=True, exist_ok=True)
            args.output.write_text(text, encoding="utf-8")
        else:
            sys.stdout.write(text)
        return 0 if result_is_green(result) else 1
    except DodError as exc:
        print(f"dod-1.0: {exc.diagnostic}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
