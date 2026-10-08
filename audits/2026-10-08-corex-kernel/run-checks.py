#!/usr/bin/env python3
"""Short static gates only; no test suites, mutation, lint or full analysis."""
import json
import os
from pathlib import Path
import subprocess
import time

ROOT = Path(__file__).resolve().parents[2]
OUT = Path(__file__).resolve().parent
COMMANDS = [
    ("manifests", ["python3", "scripts/manifest-check.py"]),
    ("layout", ["python3", "scripts/module-layout-gate.py"]),
    ("schema", ["scripts/php84", "composer", "schema-gate"]),
    ("graph", ["scripts/php84", "composer", "package-graph-gate"]),
    ("ownership", ["scripts/php84", "composer", "ownership-gate"]),
    ("table-ownership", ["scripts/php84", "composer", "table-ownership-gate"]),
    ("table-writes", ["scripts/php84", "composer", "table-write-ownership-gate"]),
    ("config", ["scripts/php84", "composer", "config-composition-gate"]),
    ("globals", ["scripts/php84", "composer", "runtime-globals-gate"]),
    ("rename", ["scripts/php84", "composer", "rename-completeness"]),
    ("naming", ["scripts/php84", "composer", "naming-gate"]),
    ("errors", ["scripts/php84", "composer", "error-registry"]),
    ("api", ["scripts/php84", "composer", "php-api"]),
    ("quality", ["scripts/php84", "composer", "quality:ceilings"]),
    ("config-reference", ["scripts/php84", "composer", "configuration-reference"]),
    ("docs", ["scripts/php84", "composer", "package-docs-gate"]),
]

results = json.loads((OUT / "checks.json").read_text()) if (OUT / "checks.json").exists() else []
completed = {row["name"] for row in results if row["exit"] == 0}
for name, command in COMMANDS:
    if name in completed:
        continue
    started = time.monotonic()
    graph_report = ROOT / "artifacts/P12-package-graph/report.json"
    prior_graph = graph_report.read_bytes() if name == "graph" and graph_report.exists() else None
    try:
        with (OUT / (name + ".log")).open("w") as log:
            try:
                run = subprocess.run(command, cwd=ROOT, env=os.environ.copy(), stdout=log,
                                     stderr=subprocess.STDOUT, timeout=60)
                code = run.returncode
            except subprocess.TimeoutExpired:
                code = 124
        if name == "graph" and graph_report.exists():
            (OUT / "package-graph-report.json").write_bytes(graph_report.read_bytes())
    finally:
        if name == "graph":
            if prior_graph is not None:
                graph_report.write_bytes(prior_graph)
            else:
                graph_report.unlink(missing_ok=True)
    result = {"name": name, "command": command, "exit": code,
              "seconds": round(time.monotonic() - started, 2), "log": name + ".log"}
    results.append(result)
    (OUT / "checks.json").write_text(json.dumps(results, indent=2) + "\n")
    print(name, "exit=" + str(code), flush=True)
