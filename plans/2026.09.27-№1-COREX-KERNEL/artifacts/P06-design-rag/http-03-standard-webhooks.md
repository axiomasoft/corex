# http-03-standard-webhooks — Standard Webhooks spec: payload, signature, operational considerations (retries, 3xx, 410)

- Tool: curl (primary source) · Date: 2026-10-03
- URL: https://raw.githubusercontent.com/standard-webhooks/standard-webhooks/main/spec/standard-webhooks.md
- SHA-256 of fetched document: `47cf696ee08a583f6cddf2d02b13e544e1bd28435b8e418870e8b557b1ed4082` (339 lines)
- Status: primary source; excerpts verbatim, line ranges of the fetched file.

## Lines 41-122

```text
## Specification

The Standard Webhooks specification is a set of conventions to be followed by webhook producers (senders) to provide webhook consumers (receivers) a secure, consistent, and interoperable interface for webhooks. The specification includes both requirements for any compatible implementation and recommendations that are not necessarily required for compatibility, but provide a better experience for the producers, consumers, or both.

### Payload

The payload is the core part of every webhook. It is the actual data being sent as part of the webhook, and usually consists of important information about the event and related information.

While this specification does not dictate the structure, or impose any requirements, on the shape, format, and content of the payload it does offer recommendations.

#### Payload structure

- The payload should be passed in the HTTP body.
- The payload should be [JSON formatted](https://en.wikipedia.org/wiki/JSON) for maximum compatibility, but other content types can be used as well.
- It's recommended to provide examples of the payload structure for each of the event types, as well as a formal specification of the structure (such as JSON Schema or OpenAPI).
- Payload structure:
  - `type`: a full-stop delimited type associated with the event. The type indicates the type of the event being sent (e.g "user.created" or "invoice.paid"), indicates the schema of the payload (passed in data), and it should be grouped hierarchically.
  - `timestamp`: the timestamp of when the event occurred (not necessarily the same of when it was delivered), it should be [ISO 8601 formatted](https://en.wikipedia.org/wiki/ISO_8601) 
  - `data`: the actual event data associated with the event. It can either be passed as part of the data property, or squashed as part of the top-level object.
  - Additional metadata can be added both as top-level properties or as part of data, depending on personal preference.

Example payload:

```json
{
  "type": "example.event",
  "timestamp": "2022-11-03T20:26:10.344522Z",
  "data": {
    "foo": "bar",
    "fizzbuzz": 2
  }
}
```

#### "Thin" vs "full" payloads

There are two main approaches to webhook payloads: "thin" and "full" payloads. Full payloads consist of the full information about the event, the status of the related entities, and everything that's changed. A thin payload will only include identifiers to the affected entities, and potentially information about the change itself.

Let's consider a fictional address book management service. It's a simple service where you can update and maintain your address book, and it sends webhooks every time data changes.

Now, let's assume we just created a new contact, here are the payloads that will be sent:

Full payload:

```json
{
  "type": "contact.created",
  "timestamp": "2022-11-03T20:26:10.344522Z",
  "data": {
    "id": "1f81eb52-5198-4599-803e-771906343485",
    "type": "contact",
    "fullName": "John Smith",
    "address": "800 W NASA Pkwy, Webster, TX 77598, USA",
    "phoneNumber": "(281) 332-2575",
    "birthday": "1980-04-19",
    "occupation": "Engineer, ACME"
  }
}
```

Thin payload:

```json
{
  "type": "contact.created",
  "timestamp": "2022-11-03T20:26:10.344522Z",
  "data": {
    "id": "1f81eb52-5198-4599-803e-771906343485"
  }
}
```

Thin and full payloads are not a binary decision, for example, one can decide to take the thin payload approach, but also include the "fullName" field as that one is commonly used and is very useful.

There are pros and cons for both, and which to go for depends on the specific requirements. The main advantage of using full payloads is that most of the information is available for the webhook consumer without needing to make additional API calls to get more data. Thin payloads, however, offer better performance (sending and generating less data, often), more flexibility (it's not always trivial to query the database for the full payload data for the webhook from every context in the code), and it's more future proof (you can always make a thin one full, but not the other way around). Another important advantage of thin payloads is that they provide for better control over the flow of data. In many scenarios, it's preferable (if not required) to have an audit log for when certain data is accessed. With full payloads, the data is just sent to every listening endpoint, with thin payloads it's required that people explicitly query the data they need, making it possible to make access auditable and more restricted.

#### Payload size

While there are no technical limitations to the size of webhook payloads, it's recommended to keep the size of payloads small, usually smaller than 20kb. There are multiple reasons for this, but the main one is that as a webhook producer you are imposing unnecessary load on the webhook consumer. They may not be interested in this specific event, and they are surely not interested in all of the data being sent. By keeping the payload size small, you put the control in the hands of the consumers.

If you find yourself needing to send large amounts of data (e.g. images or other assets), consider uploading them somewhere and passing the link in the webhook, or if the data is dynamic, just include the resource or URL they need to query.

```

## Lines 123-180

```text
### Verifying webhook authenticity

Webhooks are just HTTP requests from an unknown source, so verifying the authenticity of webhooks is a requirement for any secure webhook implementation.

There are multiple ways to verify the authenticity of webhooks, with some being much better than others. The most common way to verify the authenticity of webhooks is by using HMAC signatures using a pre-shared secret key, with a common alternative being the use of asymmetric signatures (more on that below).

As often the case with security, using the correct cryptographic primitives for the signature verification is not sufficient for a secure implementation. This section provides a secure scheme and additional guidelines for ensuring a simple and secure implementation.

#### Webhook metadata

In addition to the payload itself, webhook implementations often include two important pieces of metadata: the timestamp of the attempt, and a unique identifier associated with the webhook. A secure signature scheme should verify the authenticity of both the payload, and the additional metadata (timestamp and unique identifier).

The timestamp of the attempt is the timestamp of when the webhook attempt has been made. The timestamp of the attempt may be different to the timestamp of the event that generated the attempt. One common example of where this happens: failed deliveries. Every time an attempt is retried the timestamp of the attempt is updated, while the timestamp of the original event remains the same. The attempt's timestamp as an important security measure meant to prevent replay attacks.

The unique identifier is a unique identifier associated with a specific event triggered, and it remains the same no matter how many times a webhook that has failed is retried. The ID is often used as an idempotency key, which lets a consumer ensure that they only process a specific event once, even if sent multiple times maliciously, in error, or due to networking issues.

#### Signature scheme

As mentioned above, it's important to sign both the body of the webhook and the associated metadata. To achieve this, the message's: ID, timestamp and body are concatenated (delimited by full-stops) and then signed.

The content to be signed is therefore: `msg_id.timestamp.payload`.

For example:

```
msg_2KWPBgLlAfxdpx2AI54pPJ85f4W.1674087231.{
  "type": "contact.created",
  "timestamp": "2022-11-03T20:26:10.344522Z",
  "data": {
    "id": "1f81eb52-5198-4599-803e-771906343485"
  }
}
```

Or more likely, with a minified JSON (assuming that's what is sent):

```
msg_2KWPBgLlAfxdpx2AI54pPJ85f4W.1674087231.{"type":"contact.created","timestamp":"2022-11-03T20:26:10.344522Z","data":{"id":"1f81eb52-5198-4599-803e-771906343485"}}
```

It's important that both the message id and the timestamp not be user controlled, or at the very least not be allowed to include any `.` to prevent certain attacks.

Note: while it's OK (and recommended) to minimize the JSON body when serialized for sending, it's important to make sure that the payload sent is the same as the payload signed. Cryptographic signatures are sensitive to even the smallest changes, and even a stray space can cause the signature to be invalid. This is a very common failure mode as many webhook consumers often accidentally parse the body as json, and then serialize it again, which can cause for failed verification due to minor changes in serialization of JSON (which is not necessarily the same across implementations, or even multiple invocations of the same implementation).

This specification allows for both symmetric and asymmetric signatures, and depending on which is used, may require slightly different handling. There are no limitations on their usage, and for example one consumer may use symmetric, and another may use asymmetric, or even the same consumer may change back and forth between them.

There are a few differences between symmetric and asymmetric signatures and how they are used:

|                      | Symmetric                                                         | Asymmetric                                                                                                            |
| -------------------- | ----------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Signature scheme     | `HMAC-SHA256`                                                     | `ed25519`                                                                                                             |
| Signing secret       | Random. Between 24 bytes (192 bits) and 64 bytes (512 bits)       | Standard ed25519 key pair                                                                                             |
| Secret serialization | base64 encoded, prefixed with \`whsec_\` for easy identification. | base64 encoded, prefixed with \`whsk_\` for the secret key, and \`whpk_\` for the public key for easy identification. |
| Signature identifier | `v1`                                                              | `v1a`                                                                                                                 |


Comparison:

```

## Lines 236-310

```text
### Operational considerations

The previous section covered important considerations relating to the signature scheme, the payload, and the headers. This section is about operational considerations required for a good webhook experience.

#### Event types

Event types indicate the type of the event being sent in the webhook and the schema of the payload. A payload associated with an event type should always have the same schema. You can think of event types as different URL paths in the case of REST. The same way that REST APIs always expect (and return) a set schema for a specific URL, so should your webhooks.

It's recommended that event types be formatted as an hierarchical, and full-stop delimited, list of identifiers, and that the identifiers would be limited to a limited set of characters `[a-zA-Z0-9_]`. This refers to the event type IDs used by the API, but doesn't limit the use of more user-friendly friendly display strings. For example, one could use `user.created` as the event type, and "A user has been created" as the event display string.

It is also recommended to let webhook consumers choose which event types they would like to receive for which endpoints, and do the filtering on the producing end. This reduces a lot of unnecessary load on the consumer, receiving events they have no interest in receiving.

#### Deliverability and reliability

In order for webhook implementations to be trusted and relied upon, they have to do whatever is possible to ensure the timely and reliable delivery of webhooks.

Webhook sending may sometimes fail. This can be due to networking issues, bugs in the receiver, or a variety of other issues. It's therefore up to the webhook producer to retry sending the webhook until a successful attempt or until it's determined that delivery may not be possible. Retries are an important part of having reliable webhooks.

It's recommended to retry delivery following a retry schedule spanning multiple days, with an exponential backoff. It's recommended to also add some level of random jitter to retries to prevent cases where the failures are due to recurring load caused by the webhook attempts themselves.

In some cases, webhook delivery may fail consistently over a long period of time. In that scenario it is important to both: notify the consumers using other channels (e.g. email), and is recommended to disable future delivery to the endpoint.

Example retry schedule:

| Delay       | Time since start |
| ----------- | ---------------- |
| Immediately | 00:00:00         |
| 5 seconds   | 00:00:05         |
| 5 minutes   | 00:05:05         |
| 30 minutes  | 00:35:05         |
| 2 hours     | 02:35:05         |
| 5 hours     | 07:35:05         |
| 10 hours    | 17:35:05         |
| 14 hours    | 31:35:05         |
| 20 hours    | 51:35:05         |
| 24 hours    | 75:35:05         |

#### Delivery success and failure

A webhook delivery is considered successful if it was responded to with a `2xx` status code (status codes 200-299), and it is considered a failure in any other scenario. Example failure scenarios include non-`2xx` response status codes (e.g: 404 and 500), request timeouts (see next section), connection resets, and more.

It's important to follow HTTP etiquette when sending webhooks, and respond to HTTP status codes appropriately. Here is the suggested status code handling:

- `2xx`: Success.
- `3xx`: Failure. Following redirects causes unnecessary load on both the sender and the receiver, it's therefore recommended to update the webhook URL instead.
- `410 Gone`: This is an indication by the server that it's no longer interested in receiving webhooks from this source. Sender should disable the webhook endpoint, and stop sending it messages.
- `429 Too Many Requests`: This indicates a rate-limit has been met, and it's recommended to throttle additional requests.
- `502 Bad Gateway` and `504 Gateway Timeout`: Both errors usually indicate that the server is under load, and it's recommended to throttle the requests.
- The rest of the status codes should be treated as a failure.

Additionally, some responses may also include a `retry-after` header (e.g. `503 Service Unavailable`), which should be taken into consideration when scheduling the next attempt.

#### Request timeouts

In order to ensure the reliable delivery of webhooks, it's important to ensure consumers have enough time to process and acknowledge the processing of requests. A recommended request timeout value for webhooks is somewhere between 15 and 30s.

#### Enforcing HTTPS

Depending on the content of the webhooks being sent, it may be advisable to ensure that all webhook endpoints are HTTPS. While the signature scheme above covers the authenticity and validity of the payloads (so they can't be tampered with), it doesn't encrypt the data, which means it may be possible to eavesdrop and view the content of the payloads.

#### Static source IPs

Some webhook consumers have firewalls (or other security mechanisms) in front of their webhook endpoints, and require webhooks to be sent from a predefined list of static IPs that can be allowed to go through the firewall. This is common in many corporate environments, and while not strictly a requirement for webhooks, it's a common consideration.

#### Server side request forgery (SSRF)

A server-side request forgery (SSRF) attack is when an attacker abuses functionality on the server to read or update internal resources. In the attack, the attacker supplies or modifies a URL which the server will then make a call to. By carefully selecting the URLs, the attacker may be able to read server configuration such as AWS metadata, connect to internal services like HTTP-enabled databases or perform post requests towards internal services which are not intended to be exposed.

Webhooks implementations are especially vulnerable to SSRF as they let their consumers (customers) add any URLs they want, which will be called from the internal webhook system.

The main way to protect against SSRF is to prevent the webhooks from calling into internal networks and services. To achieve this you'd want to do two things: the first would be to proxy all webhook requests through a special proxy (like[  smokescreen](https://github.com/stripe/smokescreen)) that filters internal IP addresses, and the second would be to put the webhook workers (or proxy) in their own private subnet that can't access internal services.

#### Additional References
 - [OWASP API Top 10, API7:2023 Server Side Request Forgery](https://owasp.org/API-Security/editions/2023/en/0xa7-server-side-request-forgery/)

```

