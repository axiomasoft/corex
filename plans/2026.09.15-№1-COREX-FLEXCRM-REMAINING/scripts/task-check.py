#!/usr/bin/env python3
"""Resolve configured Task runtime; run its read-only structural gates."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import subprocess
import sys


def design_ready(plan: Path) -> None:
    fields = ("Intent", "Why", "Scope Included", "Scope Excluded", "Inputs", "Files", "Required Reads", "Implementation Rules", "Code Guidance", "Validation", "Deliverables")
    expected = {"plan.md", "research/05-integrated-design.md", "decisions/D4.md"}
    # Stable candidate documents own signatures/SQL/defaults referenced by item specs.
    # PLAN1.D9/PLAN1.D10: include them in the same finish seal to prevent indirect input drift.
    expected.update({"research/P2-contract-design.md", "research/P3-adapter-design.md", "research/P4-extension-design.md", "research/P5-wakeup-design.md", "research/P6-data-design.md", "research/P7-utility-design.md", "research/P8-tool-design.md", "research/P9-cohort-design.md", "decisions/D14-release-cohort.md", "scripts/validate-p9.py", "research/P10-effective-design.md", "decisions/D15-effective-acceptance.md", "scripts/validate-p10.py", "research/P11-reconciliation-design.md", "decisions/D16-consumer-reconciliation.md", "scripts/validate-p11.py"})
    for number in range(1, 12):
        phase = plan / f"phases/P{number}/P{number}.md"
        text = phase.read_text()
        if "СКЕЛЕТ — не детализирован" in text:
            raise ValueError(f"DESIGN_PENDING: P{number} remains a skeleton")
        specs = sorted(phase.parent.glob(f"P{number}.*.md"))
        specs = [spec for spec in specs if re.fullmatch(r"P\d+\.\d+\.md", spec.name)]
        if not specs:
            raise ValueError(f"DESIGN_PENDING: P{number} has no item specifications")
        for spec in specs:
            body = spec.read_text()
            for field in fields:
                match = re.search(r"\*\*" + re.escape(field) + r":\*\*\s*(.*?)(?=\n\s*\*\*|\Z)", body, re.S)
                if match is None or match[1].strip() in {"", "—"}:
                    raise ValueError(f"DESIGN_PENDING: {spec.name}/{field}")
            if "[NEEDS CLARIFICATION" in body:
                raise ValueError(f"DESIGN_PENDING: {spec.name} has unresolved contract ambiguity")
            expected.add(str(spec.relative_to(plan)))
        expected.add(str(phase.relative_to(plan)))
        rag = plan / f"research/P{number}-design-rag.md"
        report = rag.read_text()
        if "**Perplexity:** successful" not in report:
            raise ValueError(f"DESIGN_PENDING: P{number} Perplexity verification is missing")
        path = re.search(r"\*\*Search artifact:\*\*\s*`([^`]+)`", report)
        checksum = re.search(r"\*\*SHA256:\*\*\s*`?([0-9a-f]{64})", report)
        if path is None or checksum is None:
            raise ValueError(f"DESIGN_PENDING: P{number} lacks retrieval bytes/digest")
        artifact = (plan / path[1]).resolve()
        if not artifact.is_relative_to(plan.resolve()) or hashlib.sha256(artifact.read_bytes()).hexdigest() != checksum[1]:
            raise ValueError(f"DESIGN_PENDING: P{number} retrieval digest mismatch")
        response = json.loads(artifact.read_text())
        if response.get("isError") is True or not response.get("content"):
            raise ValueError(f"DESIGN_PENDING: P{number} retrieval failed or is empty")
        primary = re.search(r"^## Primary sources\n(.*?)(?=^## |\Z)", report, re.M | re.S)
        rows = [] if primary is None else [line for line in primary[1].splitlines() if line.startswith("|")][2:]
        origins = {line.split("|")[1].strip() for line in rows if re.search(r"https://[^\s|]+", line)}
        if len(origins - {"", "—"}) < 2 or "## Alternatives" not in report or "## Cross-phase impact" not in report:
            raise ValueError(f"DESIGN_PENDING: P{number} lacks independent sources/alternatives/integration review")
        expected.add(str(rag.relative_to(plan)))
    finish = (plan / "artifacts/design-finish.md").read_text()
    if "**Design finish:** verified" not in finish:
        raise ValueError("DESIGN_PENDING: whole-plan design-finish is unverified")
    section = re.search(r"^## Design inputs\n(.*?)(?=^## |\Z)", finish, re.M | re.S)
    rows = [] if section is None else re.findall(r"^\|\s*`?([^|`]+?)`?\s*\|\s*`?([0-9a-f]{64})`?\s*\|$", section[1], re.M)
    anchors = dict(rows)
    if len(rows) != len(anchors) or set(anchors) != expected:
        raise ValueError("DESIGN_PENDING: finish does not cover all stable design inputs")
    for path, checksum in anchors.items():
        if hashlib.sha256((plan / path).read_bytes()).hexdigest() != checksum:
            raise ValueError(f"DESIGN_PENDING: finish input changed: {path}")
    print("Design gate PASS: 11 detailed phases, Perplexity evidence and current whole-plan finish; semantic review remains required")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--check", choices=("lint", "downstream", "design"), required=True)
    args = parser.parse_args()
    try:
        plan = Path(__file__).resolve().parent.parent
        if args.check == "design":
            design_ready(plan)
            return 0
        metadata = subprocess.run(["codex", "plugin", "marketplace", "list", "--json"], capture_output=True, text=True, check=True)
        roots = [Path(row["root"]) / "packages/task" for row in json.loads(metadata.stdout).get("marketplaces", []) if row.get("name") == "swissknifeman"]
        if len(roots) != 1 or not (roots[0] / "lib/task").is_dir():
            raise ValueError("expected one configured Swissknifeman Task runtime")
        script = roots[0] / "scripts" / ("plan-lint.py" if args.check == "lint" else "plan-downstream.py")
        command = [sys.executable, str(script)]
        command += [str(plan)] if args.check == "lint" else ["--plan-dir", str(plan), "--item", "P1.4"]
        print(json.dumps({"command": command, "cwd": str(Path.cwd())}), flush=True)
        return subprocess.run(command, check=False).returncode
    except (OSError, ValueError, KeyError, subprocess.CalledProcessError) as exc:
        print(f"Task runtime check FAIL: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
