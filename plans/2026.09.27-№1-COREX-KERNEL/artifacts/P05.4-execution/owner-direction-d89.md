# Owner direction — hosted-CI carrier leaves P05.4 (2026-10-03)

Verbatim owner messages in the P05.4 session (`claude-code`, session `d3d72db8-375c-4ab4-9a93-bd5484f31865`):

- «CI не фикси, это отдельно сделает sol»
- Answer to the blocker question (all P05.4 local Validation is green; the hosted-CI Validation bullet cannot be satisfied here and `plan-lint` forbids closing on a missing carrier): «Перенести carrier (Recommended)» — remove the hosted-CI bullet from the P05.4 spec, record decision D89 and a design-audit waiver for the new fingerprint, close P05.4 on its local Validation, leave phase P05 open until a green hosted run on the final phase commit (D73) is produced separately.
