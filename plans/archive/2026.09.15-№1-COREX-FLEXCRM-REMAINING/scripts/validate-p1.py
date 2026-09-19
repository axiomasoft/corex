#!/usr/bin/env python3
"""Read-only structural checks for P1 Markdown deliverables; semantic review stays explicit."""
from __future__ import annotations

import argparse
from collections import Counter
import hashlib
import json
from pathlib import Path
import re
import subprocess
import sys


EMPTY = {"", "—", "-", "TODO", "TBD"}


def require(condition: bool, message: str) -> None:
    if not condition:
        raise ValueError(message)


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def table(path: Path, heading: str, columns: tuple[str, ...]) -> list[dict[str, str]]:
    text = path.read_text()
    section = re.search(r"^## " + re.escape(heading) + r"\n(.*?)(?=^## |\Z)", text, re.M | re.S)
    require(section is not None, f"{path}: missing section {heading}")
    lines = [line for line in section[1].splitlines() if line.startswith("|")]
    require(len(lines) >= 2, f"{path}: missing table {heading}")
    split = lambda line: [cell.strip().strip("`") for cell in re.split(r"(?<!\\)\|", line)[1:-1]]
    require(split(lines[0]) == list(columns), f"{path}: columns must be {columns}")
    result = []
    for line in lines[2:]:
        cells = split(line)
        require(len(cells) == len(columns), f"{path}: malformed row {line[:100]}")
        result.append(dict(zip(columns, cells)))
    return result


def exact(rows: list[dict[str, str]], key: str, ids: set[str]) -> None:
    counts = Counter(row[key] for row in rows)
    require(set(counts) == ids, f"{key}: missing={sorted(ids-set(counts))}, extra={sorted(set(counts)-ids)}")
    require(all(count == 1 for count in counts.values()), f"{key}: duplicate IDs")


def filled(row: dict[str, str], *keys: str) -> None:
    for key in keys:
        require(row[key] not in EMPTY, f"{row}: missing {key}")


def inputs(plan: Path) -> tuple[list[dict], dict[str, dict], set[str]]:
    inventory = json.loads((plan / "artifacts/source-inventory.json").read_text())
    require(len(inventory) == 100, "source inventory must retain 100 records")
    require(len({row['id'] for row in inventory}) == 100, "duplicate source ID")
    for row in inventory:
        repo = Path(row["repository"])
        original = repo / row["path"]
        source = plan / row["snapshot"] if row.get("snapshot") else original
        require(digest(source) == row["sha256"], f"sealed source changed: {row['id']}")
        if row.get("committed_snapshot"):
            require(digest(plan / row["committed_snapshot"]) == row["committed_sha256"], f"committed snapshot changed: {row['id']}")
        if row.get("head_blob"):
            blob = subprocess.run(["git", "-C", str(repo), "cat-file", "blob", row["head_blob"]], capture_output=True, check=True).stdout
            expected = row.get("committed_sha256") or (row["sha256"] if row["provenance"] == "committed" else None)
            if expected:
                require(hashlib.sha256(blob).hexdigest() == expected, f"blob mismatch: {row['id']}")
    criteria = {}
    for line in (plan / "research/01a-acceptance-index.md").read_text().splitlines():
        if not line.startswith("| ["):
            continue
        match = re.fullmatch(r"\| \[([^]]+)\]\(([^)]+)\)(.*?) \| (.*?) \| (.*) \|", line)
        require(match is not None, "malformed original criterion")
        key, target, location, mapped, text = match.groups()
        require(key not in criteria, f"duplicate parent AC: {key}")
        require((plan / "research" / target).is_file(), f"missing AC source: {target}")
        criteria[key] = {"r_ids": set(re.findall(r"R\d{2}", mapped)), "sha256": hashlib.sha256(text.encode()).hexdigest(), "source": target, "location": location.strip()}
    r_ids = set(re.findall(r"^\| (R\d{2}) \|", (plan / "research/01-traceability.md").read_text(), re.M))
    require(r_ids == {f"R{i:02}" for i in range(1, 53)}, "requirements must be R01–R52")
    require(len(criteria) == 365, "criteria must retain all 365 parent IDs")
    require(all(row["r_ids"] <= r_ids and row["r_ids"] for row in criteria.values()), "invalid original R mapping")
    return inventory, criteria, r_ids


def sources(plan: Path, inventory: list[dict]) -> None:
    rows = table(plan / "findings/P1-source-reconciliation.md", "Sources", ("ID", "Frozen SHA256", "Live SHA256", "Provenance", "Disposition", "Binding or gap", "Owner", "Resume"))
    exact(rows, "ID", {row["id"] for row in inventory})
    by_id = {row["id"]: row for row in inventory}
    for row in rows:
        source = by_id[row["ID"]]
        require(row["Frozen SHA256"] == source["sha256"], f"frozen hash mismatch: {row['ID']}")
        require(re.fullmatch(r"[0-9a-f]{64}", row["Live SHA256"]) is not None, "missing observed live hash")
        require(row["Provenance"] == source["provenance"], "historical provenance must remain intact")
        require(row["Disposition"] in {"accepted-source", "proposal", "historical", "drift-candidate"}, "unknown source disposition")
        filled(row, "Binding or gap", "Owner", "Resume")
        if source["provenance"] != "committed" and row["Disposition"] == "accepted-source":
            require(row["Binding or gap"].startswith("binding:"), "working proposal needs source-owner binding")


def criteria_check(plan: Path, criteria: dict[str, dict], r_ids: set[str]) -> None:
    path = plan / "findings/P1-acceptance-map.md"
    parents = table(path, "Parents", ("AC", "Original R", "Text SHA256", "Atoms", "Correction"))
    atoms = table(path, "Atoms", ("Atom", "Parent AC", "R", "Boundary", "Owner", "Condition", "Required evidence", "Destination"))
    exact(parents, "AC", set(criteria))
    atom_ids = [row["Atom"] for row in atoms]
    require(len(set(atom_ids)) == len(atom_ids), "duplicate atom ID")
    grouped = {}
    for row in atoms:
        parent = row["Parent AC"]
        require(parent in criteria, f"unknown parent: {parent}")
        require(row["R"] in r_ids, f"unknown R: {row['R']}")
        require(row["Boundary"] in {"corex", "app", "standalone", "external-ops", "excluded", "cross-cutting"}, "unknown atom boundary")
        require(re.fullmatch(re.escape(parent) + r"\.a[1-9][0-9]*", row["Atom"]) is not None, "atom ID must be <parent>.aN")
        filled(row, "Owner", "Condition", "Required evidence", "Destination")
        grouped.setdefault(parent, []).append(row)
    for row in parents:
        parent = criteria[row["AC"]]
        require(set(re.findall(r"R\d{2}", row["Original R"])) == parent["r_ids"], "original mapping lost")
        require(row["Text SHA256"] == parent["sha256"], "original criterion text changed")
        require(int(row["Atoms"]) == len(grouped.get(row["AC"], [])) > 0, "parent has missing atoms")
        if {atom["R"] for atom in grouped[row["AC"]]} != parent["r_ids"]:
            filled(row, "Correction")
    coverage = table(path, "Requirements", ("R", "Atoms or non-AC source", "Owner", "Destination"))
    exact(coverage, "R", r_ids)
    for row in coverage:
        filled(row, "Atoms or non-AC source", "Owner", "Destination")


def evidence(plan: Path, r_ids: set[str]) -> None:
    path = plan / "findings/P1-evidence-ledger.md"
    rows = table(path, "Requirements", ("R", "Disposition", "Owner", "Source anchor", "Equivalent behavior", "Tests and receipt class", "Evidence or missing receipt", "Gap owner", "Resume", "Destination"))
    exact(rows, "R", r_ids)
    for row in rows:
        require(row["Disposition"] in {"KEEP", "QUALIFY", "DELTA", "APP", "EXTERNAL", "EXCLUDED", "INVARIANT"}, "unknown requirement disposition")
        filled(row, "Owner", "Source anchor", "Equivalent behavior", "Tests and receipt class", "Evidence or missing receipt", "Gap owner", "Resume", "Destination")
    foundations = table(path, "Foundations", ("ID", "Source and test anchor", "Keep boundary", "Evidence or gap"))
    exact(foundations, "ID", {f"A{i:02}" for i in range(1, 10)})
    for row in foundations:
        filled(row, "Source and test anchor", "Keep boundary", "Evidence or gap")
    cohorts = table(path, "Cohorts", ("ID", "Source cohort", "Covered outcome", "Uncovered outcome", "Receipt anchor"))
    exact(cohorts, "ID", {f"E{i}" for i in range(1, 6)})
    for row in cohorts:
        filled(row, "Source cohort", "Covered outcome", "Uncovered outcome", "Receipt anchor")
    gates = table(path, "Gates", ("ID", "R", "Owner", "Required receipt", "Resume"))
    gate_ids = set(re.findall(r"^\| (G-[A-Z]+) \|", (plan / "research/03-gates.md").read_text(), re.M))
    exact(gates, "ID", gate_ids)
    conditions = table(path, "DI/AP", ("ID", "R", "Owner", "Required receipt", "Resume"))
    exact(conditions, "ID", {f"DI-{i:02}" for i in range(1, 35)} | {f"AP-{i:02}" for i in range(1, 14)})
    for row in gates + conditions:
        require(set(re.findall(r"R\d{2}", row["R"])) <= r_ids and re.findall(r"R\d{2}", row["R"]), "missing gate-to-R mapping")
        filled(row, "Owner", "Required receipt", "Resume")


def seal(plan: Path, r_ids: set[str]) -> None:
    path = plan / "findings/P1-requirements-lock.md"
    rows = table(path, "Requirements", ("R", "Disposition", "Owner", "Evidence or gap", "Resume", "Destination"))
    exact(rows, "R", r_ids)
    ledger = {row["R"]: row for row in table(plan / "findings/P1-evidence-ledger.md", "Requirements", ("R", "Disposition", "Owner", "Source anchor", "Equivalent behavior", "Tests and receipt class", "Evidence or missing receipt", "Gap owner", "Resume", "Destination"))}
    for row in rows:
        filled(row, "Evidence or gap", "Resume")
        for key in ("Disposition", "Owner", "Resume", "Destination"):
            require(row[key] == ledger[row["R"]][key], f"lock contradicts ledger: {row['R']}/{key}")
    gap_path = plan / "findings/P1-downstream-gaps.md"
    gaps = table(gap_path, "Requirements", ("R", "Baseline disposition", "Smallest gap or no-delta", "Producer phase", "Acceptance receipt", "Owner", "Resume", "Consumer scenario"))
    exact(gaps, "R", r_ids)
    for row in gaps:
        require(row["Baseline disposition"] == ledger[row["R"]]["Disposition"], "gap reclassifies baseline")
        filled(row, "Smallest gap or no-delta", "Producer phase", "Acceptance receipt", "Owner", "Resume", "Consumer scenario")
    conflicts = table(plan / "findings/P1-conflicts.md", "Conflicts", ("ID", "Question", "Evidence", "Alternatives", "Owner", "Disposition", "Decision or resume", "Affected R"))
    require(len({row['ID'] for row in conflicts}) == len(conflicts), "duplicate conflict ID")
    for row in conflicts:
        filled(row, "Question", "Evidence", "Alternatives", "Owner", "Disposition", "Decision or resume", "Affected R")
        require(row["Disposition"] in {"reconciled", "proposal", "needs-owner-decision", "external-receipt"}, "unknown conflict disposition")
    anchors = table(path, "Sealed inputs", ("Path", "SHA256"))
    required = {"artifacts/source-inventory.json", "research/01-traceability.md", "research/01a-acceptance-index.md", "research/02-baseline.md", "research/03-gates.md", "research/04-architecture.md", "findings/P1-source-reconciliation.md", "findings/P1-acceptance-map.md", "findings/P1-evidence-ledger.md", "findings/P1-conflicts.md", "findings/P1-downstream-gaps.md"}
    exact(anchors, "Path", required)
    for row in anchors:
        require(digest(plan / row["Path"]) == row["SHA256"], f"stale sealed input: {row['Path']}")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage", choices=("inputs", "sources", "criteria", "evidence", "seal"), required=True)
    parser.add_argument("--plan-dir", type=Path, default=Path(__file__).resolve().parent.parent)
    args = parser.parse_args()
    try:
        inventory, criteria, r_ids = inputs(args.plan_dir)
        if args.stage != "inputs":
            sources(args.plan_dir, inventory)
        if args.stage in {"criteria", "seal"}:
            criteria_check(args.plan_dir, criteria, r_ids)
        if args.stage in {"evidence", "seal"}:
            evidence(args.plan_dir, r_ids)
        if args.stage == "seal":
            seal(args.plan_dir, r_ids)
        print(json.dumps({"stage": args.stage, "result": "PASS", "sources": len(inventory), "requirements": len(r_ids), "parent_criteria": len(criteria), "semantic_review": "required by item contract"}, ensure_ascii=False))
        return 0
    except (OSError, ValueError, KeyError, subprocess.CalledProcessError) as exc:
        print(f"P1 validation FAIL: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
