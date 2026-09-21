# P10-Q4 — durable projection evidence reuse

- Query: not repeated
- Accessed: 2026-09-20
- Transport: local plan evidence
- Source: `research/P5-design-rag.md` (P5-Q1/P5-Q5/P5-Q7)

P5 already captured primary evidence for transactional outbox, idempotency and
after-commit limits. P10 consumes that frozen design: append the attribute mutation
message in the value transaction and make replay idempotent. No second web retrieval
was performed because it would not change the premise or decision.
