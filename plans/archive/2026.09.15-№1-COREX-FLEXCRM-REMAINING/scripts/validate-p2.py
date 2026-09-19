#!/usr/bin/env python3
"""Read-only P2 design/report structure and seals; semantic acceptance is item-owned."""
from __future__ import annotations

import argparse
from collections import defaultdict
import hashlib
import importlib.util
import json
from pathlib import Path
import re
import subprocess
import sys

_P1_SPEC = importlib.util.spec_from_file_location("validate_p1", Path(__file__).with_name("validate-p1.py"))
if _P1_SPEC is None or _P1_SPEC.loader is None:
    raise ImportError("plan-local validate-p1.py unavailable")
validate_p1 = importlib.util.module_from_spec(_P1_SPEC)
_P1_SPEC.loader.exec_module(validate_p1)
digest, exact, filled, require, table = (getattr(validate_p1, name) for name in ("digest", "exact", "filled", "require", "table"))


R_IDS = {f"R{i:02}" for i in range(1, 53)}
IDENTITY_R = {"R03", "R04", "R05", "R06", "R07", "R40", "R43", "R46", "R51", "R52"}
DISPOSITIONS = {"KEEP", "QUALIFY", "DELTA", "APP", "EXTERNAL", "EXCLUDED", "INVARIANT"}
SEAL_INPUTS = {
    "findings/P1-requirements-lock.md", "findings/P2-intake.md",
    "findings/P2-identity-profile.md", "findings/P2-auth-ownership.md",
    "findings/P2-delta-map.md", "findings/P2-contract-proposals.md",
    "findings/P2-consumer-cases.md", "findings/P2-gate-requests.md",
    "research/P2-contract-design.md", "research/03-gates.md",
    "research/05-integrated-design.md", "decisions/D7.md",
}


def rows(plan: Path, path: str, heading: str, columns: tuple[str, ...], *, ids=None, key=None):
    result = table(plan / path, heading, columns)
    if ids is not None:
        exact(result, key or columns[0], ids)
    for row in result:
        filled(row, *columns)
    return result


def design(plan: Path) -> None:
    phase = (plan / "phases/P2/P2.md").read_text()
    require("СКЕЛЕТ — не детализирован" not in phase, "P2 skeleton")
    expected = {f"P2.{i}.md" for i in range(1, 5)}
    specs = {p.name for p in (plan / "phases/P2").glob("P2.*.md") if re.fullmatch(r"P2\.\d+\.md", p.name)}
    require(specs == expected, "P2 must have exactly four declared specs")
    fields = ("Intent", "Why", "Scope Included", "Scope Excluded", "Inputs", "Files", "Required Reads", "Implementation Rules", "Code Guidance", "Validation", "Deliverables")
    for name in sorted(specs):
        body = (plan / "phases/P2" / name).read_text()
        found = re.findall(r"^\*\*([^*]+):\*\*", body, re.M)
        require(found == list(fields), f"{name}: ordered stable fields")
        for field in fields:
            value = re.search(r"\*\*" + re.escape(field) + r":\*\*\s*(.*?)(?=\n\*\*|\Z)", body, re.S)
            require(value is not None and value[1].strip() not in {"", "—"}, f"{name}: empty {field}")
        require("decisions/D6.md" in body and "--check design" in body, f"{name}: D6/D4 policy missing")
        require("[NEEDS CLARIFICATION" not in body, f"{name}: unresolved contract choice")
    choices = table(plan / "research/P2-contract-design.md", "Решения по группам", ("R", "Minimal boundary / selection", "Owner / destination", "Non-CRM scenario", "Failure / version invariant"))
    require(set(re.findall(r"R\d{2}", " ".join(r['R'] for r in choices))) == R_IDS, "design group coverage must retain 52 R")
    anchors = table(plan / "research/P2-contract-design.md", "Sealed inputs", ("Path", "Role"))
    exact(anchors, "Path", SEAL_INPUTS)
    require("CoreX — внутренняя разработка" in (plan / "plan.md").read_text(), "global internal-project policy absent")
    query = (plan / "artifacts/P2-design/perplexity-query.txt").read_text()
    require(not re.search(r"core\s*x|flex\s*crm|R\d{2}|DI-\d|AP-\d|packages/", query, re.I), "final RAG query must be generic, without internal context")
    report = (plan / "research/P2-design-rag.md").read_text()
    require("**Perplexity:** successful" in report, "mandatory Perplexity pending")
    path = re.search(r"\*\*Search artifact:\*\*\s*`([^`]+)`", report)
    checksum = re.search(r"\*\*SHA256:\*\*\s*`([0-9a-f]{64})`", report)
    require(path is not None and checksum is not None, "RAG artifact/digest absent")
    artifact = (plan / path[1]).resolve()
    require(artifact.is_relative_to(plan.resolve()) and digest(artifact) == checksum[1], "RAG digest/path mismatch")
    response = json.loads(artifact.read_text())
    require(response.get("isError") is not True and response.get("content"), "retrieval error/empty")
    primary = table(plan / "research/P2-design-rag.md", "Primary sources", ("Origin", "URL", "Supported claim"))
    require(len({r['Origin'] for r in primary}) >= 2, "two independent primary origins required; semantic independence review remains required")
    require("## Alternatives" in report and "## Cross-phase impact" in report, "RAG alternatives/integration absent")


def intake(plan: Path) -> None:
    result = rows(plan, "findings/P2-intake.md", "Requirements", ("R", "Frozen atoms or source", "Existing behavior", "Source and test anchor", "Insufficiency or no-delta", "Disposition", "Port owner", "Destination", "Gate or no-delta", "Resume"), ids=R_IDS)
    for row in result:
        require(row['Disposition'] in DISPOSITIONS, f"{row['R']}: unknown disposition")
    rows(plan, "findings/P2-intake.md", "Foundations", ("ID", "Existing port and evidence", "Keep boundary", "Proposed delta or none"), ids={f"A{i:02}" for i in range(1, 10)})


def identity(plan: Path) -> None:
    rows(plan, "findings/P2-identity-profile.md", "Requirements", ("R", "Contract", "Context and policy owner", "Failure model", "Gate", "Decision or resume", "Destination"), ids=IDENTITY_R)
    rows(plan, "findings/P2-identity-profile.md", "Wire profile", ("Contract", "Existing bytes", "Proposed bytes", "Version and BC", "Trust source", "Unknown outcome", "Owner and receipt"), ids={"read", "revoke", "account-list"})
    rows(plan, "findings/P2-auth-ownership.md", "Boundaries", ("Boundary", "Port", "Owner", "Default or absence", "Evidence", "Gate or no-delta"), ids={"authentication", "authorization", "workspace", "role-writer", "optional-private"})
    rows(plan, "findings/P2-auth-ownership.md", "Cases", ("Case", "Given", "Expected", "Owning phase"), ids={"payload-identity", "payload-connection", "absent-policy", "absent-private", "worker-context-reset", "certificate-mismatch", "unsupported-entry", "uncertain-revoke"})


def deltas(plan: Path) -> tuple[list[dict], set[str]]:
    mapping = rows(plan, "findings/P2-delta-map.md", "Requirements", ("R", "Disposition", "Contract IDs", "Smallest gap or no-delta", "Producer", "Consumer", "Gate", "Resume"), ids=R_IDS)
    contracts = rows(plan, "findings/P2-contract-proposals.md", "Contracts", ("Contract", "R", "Owner", "Existing anchor", "Proposed section", "Public surface", "BC and version", "Schema or dependency", "Approval", "Producer"))
    ids = {row['Contract'] for row in contracts}
    exact(contracts, 'Contract', ids)
    require(all(re.fullmatch(r"C-P2-[0-9]{3}", x) for x in ids), "invalid contract ID")
    text = (plan / "findings/P2-contract-proposals.md").read_text()
    for row in contracts:
        linked = set(re.findall(r"R\d{2}", row['R']))
        require(linked and linked <= R_IDS, "contract R links invalid")
        section = re.search(r"^## " + re.escape(row['Contract']) + r"\n(.*?)(?=^## |\Z)", text, re.M | re.S)
        require(section is not None and re.search(r"```[^\n]*\n\S[\s\S]*?\n```", section[1]), f"{row['Contract']}: exact proposed bytes section required")
        require(row['Approval'] == 'no-delta' or re.fullmatch(r"proposal:G-[A-Z]+", row['Approval']), "protected proposal approval must be explicit proposal:G-ID")
    seen = set()
    for row in mapping:
        require(row['Disposition'] in DISPOSITIONS, "unknown delta disposition")
        linked = set(re.findall(r"C-P2-\d{3}", row['Contract IDs']))
        require(linked <= ids, f"{row['R']}: dangling contract link")
        seen |= linked
    require(seen == ids, "unreferenced contract proposal")
    cases = rows(plan, "findings/P2-consumer-cases.md", "Cases", ("Contract", "Scenario", "Setup", "Expected behavior", "Negative or failure", "Producer", "Consumer receipt"))
    by_contract = defaultdict(list)
    for row in cases:
        require(row['Contract'] in ids, "unknown case contract")
        by_contract[row['Contract']].append(row)
    for cid in ids:
        require(len({r['Scenario'] for r in by_contract[cid]}) >= 2, f"{cid}: two distinct consumer scenarios required")
        require(any(re.search(r"archive|laboratory|lab equipment|non-CRM|архив|лаборатор", r['Scenario'], re.I) for r in by_contract[cid]), f"{cid}: named non-CRM scenario absent")
    return mapping, ids


def seal(plan: Path) -> None:
    mapping, ids = deltas(plan)
    catalogue = rows(plan, "findings/P2-contract-catalogue.md", "Requirements", ("R", "Disposition", "Contract IDs", "Local acceptance", "Protected adoption", "Owner", "Resume", "Destination"), ids=R_IDS)
    baseline = {r['R']: r for r in mapping}
    for row in catalogue:
        require(all(row[k] == baseline[row['R']][k] for k in ('Disposition', 'Contract IDs')), "catalogue versus delta map mismatch")
    edges = rows(plan, "findings/P2-contract-catalogue.md", "Producer edges", ("Producer", "Contract version", "Consumer", "Required receipt", "Failure model"))
    require({f"P{i}" for i in range(3, 12)} <= {r['Consumer'] for r in edges}, "all P3–P11 edges required")
    requests = rows(plan, "findings/P2-gate-requests.md", "Requests", ("Request", "Gate", "R", "Exact proposal", "SHA256", "Owner", "Required receipt", "Status", "Resume", "Destination"))
    exact(requests, 'Request', {r['Request'] for r in requests})
    gates = set(re.findall(r"^\| (G-[A-Z]+) \|", (plan / 'research/03-gates.md').read_text(), re.M))
    for row in requests:
        require(re.fullmatch(r"Q-P2-[0-9]{3}", row['Request']) and row['Gate'] in gates, "unknown request/gate")
        require(row['Status'] in {'proposal', 'receipt-linked'}, "request status invalid")
        require(set(re.findall(r"R\d{2}", row['R'])) <= R_IDS and re.findall(r"R\d{2}", row['R']), "request R links invalid")
        proposal = (plan / row['Exact proposal'].split('#')[0]).resolve()
        require(proposal.is_relative_to(plan.resolve()) and digest(proposal) == row['SHA256'], "gate request exact bytes digest mismatch")
    anchors = table(plan / 'findings/P2-contract-catalogue.md', 'Sealed inputs', ('Path', 'SHA256'))
    exact(anchors, 'Path', SEAL_INPUTS)
    for row in anchors:
        require(digest(plan / row['Path']) == row['SHA256'], f"stale P2 seal: {row['Path']}")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--stage', choices=('design', 'intake', 'identity', 'deltas', 'seal'), required=True)
    parser.add_argument('--plan-dir', type=Path, default=Path(__file__).resolve().parent.parent)
    args = parser.parse_args()
    try:
        design(args.plan_dir)
        if args.stage != 'design':
            inventory, criteria, r_ids = validate_p1.inputs(args.plan_dir)
            validate_p1.sources(args.plan_dir, inventory)
            validate_p1.criteria_check(args.plan_dir, criteria, r_ids)
            validate_p1.evidence(args.plan_dir, r_ids)
            validate_p1.seal(args.plan_dir, r_ids)
            intake(args.plan_dir)
        if args.stage in {'identity', 'seal'}:
            identity(args.plan_dir)
        if args.stage == 'deltas':
            deltas(args.plan_dir)
        if args.stage == 'seal':
            seal(args.plan_dir)
        print(json.dumps({'stage': args.stage, 'result': 'PASS', 'requirements': 52, 'semantic_acceptance': 'required by item contract', 'runtime_or_live_proof': False}))
        return 0
    except (OSError, ValueError, KeyError, subprocess.CalledProcessError) as exc:
        print(f'P2 validation FAIL: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
