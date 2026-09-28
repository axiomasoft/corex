# P01.4 — action inputs (execution capture)

- Date: 2026-09-28
- Tool: perplexity search
- Query: Verify from primary upstream sources zizmorcore/zizmor-action v0.6.4: exact inputs advanced-security, persona, version, config; how to run blocking checks with contents: read only, advanced-security false, regular persona and zizmor 1.30.1; pinact v5.0.0 checksum filename and run pin action tags.
- Status: retrieval only; claims checked against upstream action.yml (http-01).

## Answer (verbatim)

+"Input","Meaning"
"advanced-security","Whether to upload findings to GitHub Advanced Security. Set it to false to print results in the workflow log instead."
"persona","Audit persona: regular (default), pedantic, or auditor."
"version","The zizmor version to install/use."
"config","Path to a custom zizmor configuration file."
