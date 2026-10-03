# pplx-03 — Standard Webhooks v1: headers, payload, retries, 410; CloudEvents body; WebHook-Request-Origin

- Tool: perplexity-web `search` · Date: 2026-10-03
- Query: "Standard Webhooks specification (standardwebhooks.com) v1: required headers webhook-id, webhook-timestamp, webhook-signature; recommended payload structure (type, timestamp, data); recommended retry schedule and which HTTP responses should be treated as permanent failure (410 Gone disable endpoint) vs transient; redirects; can the webhook body be a CloudEvents structured JSON event while signed with Standard Webhooks headers? Also CloudEvents HTTP webhook spec abuse protection (WebHook-Request-Origin)."
- Status: provenance (synthesized answer). Primary confirmation: `http-03-standard-webhooks.md`.

## Answer (verbatim)

## Standard Webhooks v1.0

The current Standard Webhooks document identifies itself as Version 1.0.0. Its three webhook headers are webhook-id, webhook-timestamp, and webhook-signature; the payload format is intentionally flexible.
- webhook-id: unique event/message ID, reused on retries.
- webhook-timestamp: Unix timestamp in seconds for this delivery attempt; it can change between retries.
- webhook-signature: one or more signatures, space-delimited to support key rotation. The signature covers webhook-id, webhook-timestamp, and the exact transmitted body, joined as id.timestamp.body.
The spec recommends, but does not require, a JSON payload with type, timestamp, and data. The event timestamp is when the event occurred, not necessarily the delivery attempt. Other body shapes and content types are allowed.

## Retries and response handling

Retries are recommended over multiple days, with exponential backoff and random jitter. The spec’s example schedule is: immediately; 5 seconds; 5 minutes; 30 minutes; 2 hours; 5 hours; 10 hours; 14 hours; 20 hours; then 24 hours. It is an example, not a mandatory schedule.
The spec does not define a universal “permanent failure” classification for every other status. In particular, 404 is a failure, but only 410 is explicitly specified as a signal to disable the endpoint.

## CloudEvents body compatibility

Yes. A CloudEvents structured JSON event can be the signed body alongside Standard Webhooks headers. Standard Webhooks does not impose a payload schema, and CloudEvents’ HTTP webhook specification likewise does not further constrain the notification content. Sign the exact bytes sent; do not parse and reserialize before signature verification.
That combination does not automatically make the delivery conform to the entire CloudEvents HTTP Webhook specification. CloudEvents additionally prescribes delivery and response behavior—for example, no redirects—and its webhook authorization model. Treat the Standard Webhooks signature as the authentication mechanism only if that is your chosen integration contract; it is not itself a substitute for implementing every CloudEvents webhook requirement.

## CloudEvents abuse protection

WebHook-Request-Origin is part of CloudEvents HTTP Webhook’s optional-to-support, recommended abuse-protection handshake:
- Before delivery, the sender sends OPTIONS to the exact registered target URI with WebHook-Request-Origin identifying the sending system. It may also include WebHook-Request-Rate.
- If the receiver consents, it returns WebHook-Allowed-Origin (the requested origin or *) and WebHook-Allowed-Rate as applicable.
- After permission is granted, the sender includes the matching WebHook-Request-Origin on each delivery.
This handshake is not authentication or authorization; its purpose is to help prevent a sender from being induced to flood an endpoint that did not agree to receive notifications. A receiver can support both this handshake and Standard Webhooks signature verification.

## Sources (as returned)

1. https://github.com/standard-webhooks/standard-webhooks/blob/main/spec/standard-webhooks.md
2. https://github.com/cloudevents/spec/blob/main/cloudevents/http-webhook.md
