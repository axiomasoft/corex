#!/usr/bin/env python3
"""Resolve configured Task runtime; run its read-only structural gates."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import subprocess
import sys


def design_inputs(plan: Path) -> set[str]:
    """Stable design closure; execution outputs and rolling views are not seal inputs."""
    expected = {
        'plan.md', 'roadmap.md', 'open-questions.md',
        'artifacts/source-inventory.json', 'scripts/task-check.py',
        'scripts/test-design-guards.py', 'findings/whole-plan-astra-review.md',
        'research/01b-acceptance-source-supplement.md',
        'research/06-azguard-patterns.md', 'research/07-open-source-patterns.md',
        'decisions/D17-whole-plan-review.md',
    }
    # Keep exact owning contract names explicit for the per-phase checker contracts.
    expected.update({"research/P2-contract-design.md", "research/P3-adapter-design.md", "research/P4-extension-design.md", "research/P5-wakeup-design.md", "research/P6-data-design.md", "research/P7-utility-design.md", "research/P8-tool-design.md", "research/P9-cohort-design.md", "decisions/D14-release-cohort.md", "scripts/validate-p9.py", "research/P10-effective-design.md", "decisions/D15-effective-acceptance.md", "scripts/validate-p10.py", "research/P11-reconciliation-design.md", "decisions/D16-consumer-reconciliation.md", "scripts/validate-p11.py"})
    for folder in ('brief', 'research', 'decisions'):
        expected.update(str(path.relative_to(plan)) for path in (plan / folder).rglob('*') if path.is_file())
    for number in range(1, 12):
        expected.add(f'scripts/validate-p{number}.py')
        expected.add(f'phases/P{number}/P{number}.md')
        expected.update(str(path.relative_to(plan)) for path in (plan / f'phases/P{number}').glob(f'P{number}.*.md') if re.fullmatch(r'P\d+\.\d+\.md', path.name))
        folder = plan / f'artifacts/P{number}-design'
        expected.update(str(path.relative_to(plan)) for path in folder.rglob('*') if path.is_file())
    review = plan / 'artifacts/astra-whole-plan-review'
    for path in review.rglob('*'):
        relative = path.relative_to(review)
        # Read progress and verification output are explicitly volatile; raw retrievals,
        # snapshots, source/test inventories and semantic review evidence are sealed.
        if path.is_file() and relative.parts[0] != 'checks' and relative.as_posix() != 'read-pages.jsonl':
            expected.add(str(path.relative_to(plan)))
    for name in expected:
        path = plan / name
        if not path.is_file() or path.is_symlink() or not path.resolve().is_relative_to(plan.resolve()):
            raise ValueError(f'DESIGN_PENDING: missing or nonlocal stable input: {name}')
    return expected


def design_ready(plan: Path) -> None:
    fields = ("Intent", "Why", "Scope Included", "Scope Excluded", "Inputs", "Files", "Required Reads", "Implementation Rules", "Code Guidance", "Validation", "Deliverables")
    expected = design_inputs(plan)
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
    print("Design gate PASS: 11 detailed phases, Perplexity evidence and current sealed whole-plan review; runtime acceptance remains item-owned")


def policy_lint(plan: Path, runtime: Path) -> int:
    """Apply the explicit plan-owner severity policy through native lint's DI sink."""
    sys.path.insert(0, str(runtime / "lib"))
    sys.path.insert(0, str(runtime.parent.parent))
    from task import plan_lint, plan_weight
    policy = json.loads((plan / "research/09-read-budget-policy.json").read_text())
    scope = {"P1.1", "P1.2", "P1.3", "P1.4"}
    if (policy.get("schema_version") != "plan-read-budget-policy/v1"
            or policy.get("version") != "1.1.0" or policy.get("mode") != "advisory"
            or set(policy.get("scope", [])) != scope or policy.get("batch") != "B-P1-QUALIFY"
            or policy.get("reference_item_tokens") != 100000
            or policy.get("decision") != "decisions/D19-read-budget-advisory.md"
            or hashlib.sha256((plan / policy["decision"]).read_bytes()).hexdigest() != policy.get("decision_sha256")):
        raise ValueError("invalid or unbound owner read-budget policy")
    additional = policy.get("additional_scopes")
    if not isinstance(additional, list) or len(additional) != 1:
        raise ValueError("invalid additional owner read-budget scopes")
    p2 = additional[0]
    if (not isinstance(p2, dict) or set(p2) != {"scope", "batch", "decision", "decision_sha256"}
            or p2.get("scope") != ["P2.1", "P2.2", "P2.3", "P2.4"]
            or p2.get("batch") != "B-P2-PROPOSALS"
            or p2.get("decision") != "decisions/D20-P2-read-budget-advisory.md"
            or hashlib.sha256((plan / p2["decision"]).read_bytes()).hexdigest() != p2.get("decision_sha256")):
        raise ValueError("invalid or unbound P2 owner read-budget policy")
    decisions = {item: "D19" for item in scope}
    decisions.update({item: "D20" for item in p2["scope"]})
    measured = plan_weight.measure_plan(plan, plan_weight.Measurer())
    records = {row["item_id"]: row for row in measured["items"]}
    threshold = measured["totals"]["admission"]["contract"]["item_tokens"]
    if threshold != policy["reference_item_tokens"]:
        raise ValueError("native read-budget contract drift")

    class OwnerDiagnostics(plan_lint.Diagnostics):
        def error(self, path, line, msg):
            match = re.fullmatch(r"read-budget-v1: пункт (P\d+\.\d+): вес (\d+) > порога (\d+) оценочных токенов \(.+\)", msg)
            row = records.get(match[1]) if match else None
            if (match and match[1] in decisions and Path(path).resolve() == (plan / "plan.md").resolve()
                    and line is None and row and row.get("admission_state") == "ready"
                    and row.get("d2a_pass") is False and int(match[2]) == row["weight_tokens"]
                    and int(match[3]) == threshold):
                self.warning(path, line, msg + f" [owner advisory {decisions[match[1]]}; completeness required]")
            else:
                super().error(path, line, msg)

    diagnostics = OwnerDiagnostics()
    plan_lint.lint_plan(plan, diagnostics)
    for message in diagnostics.errors:
        print("ERROR: " + message)
    for message in diagnostics.warnings:
        print("WARNING: " + message)
    print(json.dumps({"policy": policy, "native_totals": measured["totals"],
                      "result": "PASS" if diagnostics.ok else "FAIL",
                      "errors": len(diagnostics.errors), "warnings": len(diagnostics.warnings)}, ensure_ascii=False))
    return 0 if diagnostics.ok else 1


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
        return policy_lint(plan, roots[0]) if args.check == "lint" else subprocess.run(command, check=False).returncode
    except (OSError, ValueError, KeyError, subprocess.CalledProcessError) as exc:
        print(f"Task runtime check FAIL: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
