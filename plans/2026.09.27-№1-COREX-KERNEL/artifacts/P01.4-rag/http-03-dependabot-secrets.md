# Dependabot workflow secrets

- Date: 2026-09-28
- Source: https://docs.github.com/en/code-security/reference/supply-chain-security/troubleshoot-dependabot/dependabot-on-actions
- Tool: web search, upstream GitHub Docs result
- Status: RAG:✅
- Verified fact (paraphrase): Dependabot-triggered workflows use Dependabot secrets in the same
  `secrets` context syntax. A workflow also triggered by other actors can use identically named
  Actions and Dependabot secrets.

This capture confirms configuration semantics; it does not authorize a credential or claim it exists.
