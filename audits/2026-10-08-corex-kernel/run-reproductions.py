#!/usr/bin/env python3
"""Run bounded diagnostics, each with a 15 second timeout; never a test suite.

PostgreSQL diagnostics require the separately created corex_kernel_audit_test DB.
The PHP guard rejects any other database before resetting its schema.
"""
import json
import os
from pathlib import Path
import subprocess
import time

ROOT = Path(__file__).resolve().parents[2]
OUT = Path(__file__).resolve().parent
CASES = [
    "search-fields", "tool-output", "recurrence-resume", "task-resume",
    "digest-stamp", "digest-starvation", "task-wakeup-no-handler", "task-wakeup",
    "task-full-control", "module-direct-control", "preinstalled-module",
]

records = []
for case in CASES:
    command = ["php8.4", str(OUT / "reproduce.php"), case]
    environment = os.environ.copy()
    if case in {"module-direct-control", "preinstalled-module"}:
        environment["PGSQL_DATABASE"] = "corex_kernel_audit_test"
    started = time.monotonic()
    run = subprocess.run(command, cwd=ROOT, env=environment, capture_output=True,
                         text=True, timeout=15)
    (OUT / ("repro-" + case + ".log")).write_text(run.stdout + run.stderr)
    record = {"case": case, "exit": run.returncode,
              "seconds": round(time.monotonic() - started, 3),
              "log": "repro-" + case + ".log"}
    if run.returncode == 0:
        record["observation"] = json.loads(run.stdout)
    records.append(record)
    (OUT / "reproductions.json").write_text(json.dumps(records, indent=2) + "\n")
    print(case, "exit=" + str(run.returncode), flush=True)
    if run.returncode:
        raise SystemExit(run.returncode)
