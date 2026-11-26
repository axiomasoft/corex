"""Evaluate the expected jobs for the current CI run."""
import json
import os
import sys

needs = json.loads(os.environ["NEEDS"])
selected = set(filter(None, os.environ["SELECTED"].split(",")))
code = needs["changes"]["outputs"].get("code")
always = {"changes", "lint"}
independent = {"anti-weakening-gate", "secret-scan", "contract-guard"}
mutation = {"mutation-check", "mutation-fragments"}
normal = set(needs) - always - mutation
unknown = selected - normal - always
errors = [f"Unknown or non-selectable job ID: {name}" for name in sorted(unknown)]
if code not in {"true", "false"}:
    errors.append("Missing path classification")
required = always | (selected or (normal if code == "true" else independent))
if os.environ["MUTATION"] == "true":
    required |= mutation | {"mutation-inventory"}
with open(os.environ["GITHUB_STEP_SUMMARY"], "a", encoding="utf-8") as summary:
    summary.write("## Job results\n\n| Job | Result |\n| --- | --- |\n")
    for name, job in sorted(needs.items()):
        result = job["result"]
        summary.write(f"| `{name}` | {result} |\n")
        if result != "success" and (result != "skipped" or name in required):
            errors.append(f"{name}: {result}")
for error in errors:
    print(f"::error::{error}")
sys.exit(bool(errors))
