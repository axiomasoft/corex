#!/usr/bin/env bash
# Reproduces the P06 design inventory (RAG:—). Run from the repository root.
set -euo pipefail
out="plans/2026.09.27-№1-COREX-KERNEL/artifacts/P06-design/facts"
mkdir -p "$out"

{
  echo "# tool error literals (GuardedToolInvoker, McpToolsProjection, schema worker)"
  grep -rn -o -E "'(tool_unavailable|tool_revision_conflict|input_invalid|schema_unavailable|schema_limit|internal_error|rate_limited|output_contract_failed)'" \
    packages/tools/src | sort
} > "$out/tool-error-literals.txt"

{
  echo "# webhook delivery failure literals (worker, retry policy, URL policy, fanout)"
  grep -rn -o -E "'(topic_unavailable|signing_unavailable|webhook_url_forbidden|dns_unavailable|webhook_dns_unavailable|network_failure|transport_failure|address_not_allowed|retry_exhausted|retry_horizon_exhausted|endpoint_gone|redirect_forbidden|payload_too_large|http_permanent|http_429|http_5xx|http_)'" \
    packages/webhooks/src | sort
} > "$out/webhook-failure-literals.txt"

{
  echo "# error serializer / problem_type_base consumers"
  grep -rln -e AcceptNegotiation -e EnvelopeSerializer -e NegotiatingResponseSerializer -e ProblemDetailsSerializer -e problemTypeBase -e problem_type_base \
    packages products tests docs scripts 2>/dev/null | grep -v /vendor/ | sort
} > "$out/error-serializer-consumers.txt"

{
  echo "# tests reading the envelope error member (rewritten to problem+json members in P06.1)"
  grep -rln -e "'error' => null" -e "\['error'\]" -e "json('error" -e "'error.code'" \
    packages/*/tests products/*/tests tests 2>/dev/null | grep -v /vendor/ | sort || true
} > "$out/envelope-error-tests.txt"

{
  echo "# input envelope branches in OperationDispatcher"
  grep -n -e "ApiError(" -e "isJson" -e "query->" packages/operations/src/Invocation/Internal/OperationDispatcher.php
} > "$out/input-envelope.txt"

{
  echo "# leftover operations directories after the P02 split (files, then directories)"
  find packages/operations/src/Tools packages/operations/src/Webhooks -type f | wc -l
  find packages/operations/src/Tools packages/operations/src/Webhooks -type d | sort
} > "$out/operations-leftovers.txt"

{
  echo "# published-message wire references"
  grep -rn -e "двенадцать ключей" -e "envelope-version" -e "MSG-003" -e "CloudEvents" \
    packages/core/src docs/architecture 2>/dev/null | grep -v /vendor/ || true
} > "$out/message-wire.txt"

{
  echo "# MessageEnvelope producers (source values must be URI-references, D90 p.2)"
  grep -rn "source: '" packages/*/src --include=*.php | sort || true
} > "$out/message-sources.txt"

{
  echo "base: $(git rev-parse HEAD)"
  for f in "$out"/*.txt; do
    [ "$(basename "$f")" = summary.txt ] && continue
    printf '%s %s\n' "$(grep -vc '^#' "$f" || true)" "$(basename "$f")"
  done
} > "$out/summary.txt"
cat "$out/summary.txt"
