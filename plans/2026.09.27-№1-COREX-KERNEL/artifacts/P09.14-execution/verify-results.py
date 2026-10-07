import json
import urllib.request
import xml.etree.ElementTree as ET
from pathlib import Path

artifact = Path(__file__).parent
expected = {
    "meilisearch.xml": [
        "returns candidate identifiers only from an isolated local Meilisearch service",
        "returns an asynchronous task receipt for index submission",
        "returns an asynchronous task receipt for an index swap",
    ],
    "postgres-search.xml": [
        "uses allowlisted schema identifiers and bound text-query values",
        "uses an opaque cursor bound to its scope and query",
        "keeps physically separate account databases and their cursors isolated",
        "preserves phrase search across nullable schema columns without interpreting text as SQL",
    ],
}
summary = {}
for filename, names in expected.items():
    source = Path("build/quality/P09.14") / filename
    root = ET.parse(source).getroot()
    cases = root.findall(".//testcase")
    assert sorted(case.get("name", "") for case in cases) == sorted("it " + name for name in names)
    assert all(int(case.get("assertions", "0")) > 0 for case in cases)
    assert not any(root.findall(".//" + tag) for tag in ["skipped", "failure", "error"])
    (artifact / filename).write_bytes(source.read_bytes())
    summary[filename] = {
        "cases": len(cases),
        "assertions": sum(int(case.get("assertions")) for case in cases),
        "seconds": sum(float(case.get("time")) for case in cases),
    }

endpoint = json.loads((artifact / "meilisearch-preflight.json").read_text())["endpoint"]
with urllib.request.urlopen(endpoint + "/indexes", timeout=3) as response:
    indexes = json.load(response)
assert indexes["total"] == 0, "Meilisearch fixture indexes were not cleaned up"
summary["meilisearch_cleanup"] = indexes
(artifact / "behavior-results.json").write_text(json.dumps(summary, indent=2) + "\n")
print(json.dumps(summary, indent=2))
