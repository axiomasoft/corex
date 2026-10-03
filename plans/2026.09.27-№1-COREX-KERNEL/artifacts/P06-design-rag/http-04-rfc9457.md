# http-04-rfc9457 — RFC 9457 Problem Details: §3 object, Accept example, members, extensions

- Tool: curl (primary source) · Date: 2026-10-03
- URL: https://www.rfc-editor.org/rfc/rfc9457.txt
- SHA-256 of fetched document: `f2b3db92fb0bf3489cb3841a0da0c0d88dff40797b64d40b6123085183886c7b` (838 lines)
- Status: primary source; excerpts verbatim, line ranges of the fetched file.

## Lines 152-240

```text
3.  The Problem Details JSON Object

   The canonical model for problem details is a JSON [JSON] object.
   When serialized in a JSON document, that format is identified with
   the "application/problem+json" media type.

   For example:

   POST /purchase HTTP/1.1
   Host: store.example.com
   Content-Type: application/json
   Accept: application/json, application/problem+json

   {
     "item": 123456,
     "quantity": 2
   }

   HTTP/1.1 403 Forbidden
   Content-Type: application/problem+json
   Content-Language: en

   {
    "type": "https://example.com/probs/out-of-credit",
    "title": "You do not have enough credit.",
    "detail": "Your current balance is 30, but that costs 50.",
    "instance": "/account/12345/msgs/abc",
    "balance": 30,
    "accounts": ["/account/12345",
                 "/account/67890"]
   }

   Here, the out-of-credit problem (identified by its type) indicates
   the reason for the 403 in "title", identifies the specific problem
   occurrence with "instance", gives occurrence-specific details in
   "detail", and adds two extensions: "balance" conveys the account's
   balance, and "accounts" lists links where the account can be topped
   up.

   When designed to accommodate it, problem-specific extensions can
   convey more than one instance of the same problem type.  For example:

   POST /details HTTP/1.1
   Host: account.example.com
   Accept: application/json

   {
     "age": 42.3,
     "profile": {
       "color": "yellow"
     }
   }

   HTTP/1.1 422 Unprocessable Content
   Content-Type: application/problem+json
   Content-Language: en

   {
    "type": "https://example.net/validation-error",
    "title": "Your request is not valid.",
    "errors": [
                {
                  "detail": "must be a positive integer",
                  "pointer": "#/age"
                },
                {
                  "detail": "must be 'green', 'red' or 'blue'",
                  "pointer": "#/profile/color"
                }
             ]
   }

   The fictional problem type here defines the "errors" extension, an
   array that describes the details of each validation error.  Each
   member is an object containing "detail" to describe the issue and
   "pointer" to locate the problem within the request's content using a
   JSON Pointer [JSON-POINTER].

   When an API encounters multiple problems that do not share the same
   type, it is RECOMMENDED that the most relevant or urgent problem be
   represented in the response.  While it is possible to create generic
   "batch" problem types that convey multiple, disparate types, they do
   not map well into HTTP semantics.

   Note also that the API has responded with the "application/
   problem+json" type, even though the client did not list it in Accept,
   as is allowed by HTTP (see Section 12.5.1 of [HTTP]).

3.1.  Members of a Problem Details Object
```

## Lines 240-420

```text
3.1.  Members of a Problem Details Object

   Problem detail objects can have the following members.  If a member's
   value type does not match the specified type, the member MUST be
   ignored -- i.e., processing will continue as if the member had not
   been present.

3.1.1.  "type"

   The "type" member is a JSON string containing a URI reference [URI]
   that identifies the problem type.  Consumers MUST use the "type" URI
   (after resolution, if necessary) as the problem type's primary
   identifier.

   When this member is not present, its value is assumed to be
   "about:blank".

   If the type URI is a locator (e.g., those with an "http" or "https"
   scheme), dereferencing it SHOULD provide human-readable documentation
   for the problem type (e.g., using HTML [HTML5]).  However, consumers
   SHOULD NOT automatically dereference the type URI, unless they do so
   when providing information to developers (e.g., when a debugging tool
   is in use).

   When "type" contains a relative URI, it is resolved relative to the
   document's base URI, as per [URI], Section 5.  However, using
   relative URIs can cause confusion, and they might not be handled
   correctly by all implementations.

   For example, if the two resources "https://api.example.org/foo/
   bar/123" and "https://api.example.org/widget/456" both respond with a
   "type" equal to the relative URI reference "example-problem", when
   resolved they will identify different resources
   ("https://api.example.org/foo/bar/example-problem" and
   "https://api.example.org/widget/example-problem", respectively).  As
   a result, it is RECOMMENDED that absolute URIs be used in "type" when
   possible and that when relative URIs are used, they include the full
   path (e.g., "/types/123").

   The type URI is allowed to be a non-resolvable URI.  For example, the
   tag URI scheme [TAG] can be used to uniquely identify problem types:

   tag:example@example.org,2021-09-17:OutOfLuck

   However, resolvable type URIs are encouraged by this specification
   because it might become desirable to resolve the URI in the future.
   For example, if an API designer used the URI above and later adopted
   a tool that resolves type URIs to discover information about the
   error, taking advantage of that capability would require switching to
   a resolvable URI, creating a new identity for the problem type and
   thus introducing a breaking change.

3.1.2.  "status"

   The "status" member is a JSON number indicating the HTTP status code
   ([HTTP], Section 15) generated by the origin server for this
   occurrence of the problem.

   The "status" member, if present, is only advisory; it conveys the
   HTTP status code used for the convenience of the consumer.
   Generators MUST use the same status code in the actual HTTP response,
   to assure that generic HTTP software that does not understand this
   format still behaves correctly.  See Section 5 for further caveats
   regarding its use.

   Consumers can use the status member to determine what the original
   status code used by the generator was when it has been changed (e.g.,
   by an intermediary or cache) and when a message's content is
   persisted without HTTP information.  Generic HTTP software will still
   use the HTTP status code.

3.1.3.  "title"

   The "title" member is a JSON string containing a short, human-
   readable summary of the problem type.

   It SHOULD NOT change from occurrence to occurrence of the problem,
   except for localization (e.g., using proactive content negotiation;
   see [HTTP], Section 12.1).

   The "title" string is advisory and is included only for users who are
   unaware of and cannot discover the semantics of the type URI (e.g.,
   during offline log analysis).

3.1.4.  "detail"

   The "detail" member is a JSON string containing a human-readable
   explanation specific to this occurrence of the problem.

   The "detail" string, if present, ought to focus on helping the client
   correct the problem, rather than giving debugging information.

   Consumers SHOULD NOT parse the "detail" member for information;
   extensions are more suitable and less error-prone ways to obtain such
   information.

3.1.5.  "instance"

   The "instance" member is a JSON string containing a URI reference
   that identifies the specific occurrence of the problem.

   When the "instance" URI is dereferenceable, the problem details
   object can be fetched from it.  It might also return information
   about the problem occurrence in other formats through use of
   proactive content negotiation (see [HTTP], Section 12.5.1).

   When the "instance" URI is not dereferenceable, it serves as a unique
   identifier for the problem occurrence that may be of significance to
   the server but is opaque to the client.

   When "instance" contains a relative URI, it is resolved relative to
   the document's base URI, as per [URI], Section 5.  However, using
   relative URIs can cause confusion, and they might not be handled
   correctly by all implementations.

   For example, if the two resources "https://api.example.org/foo/
   bar/123" and "https://api.example.org/widget/456" both respond with
   an "instance" equal to the relative URI reference "example-instance",
   when resolved they will identify different resources
   ("https://api.example.org/foo/bar/example-instance" and
   "https://api.example.org/widget/example-instance", respectively).  As
   a result, it is RECOMMENDED that absolute URIs be used in "instance"
   when possible, and that when relative URIs are used, they include the
   full path (e.g., "/instances/123").

3.2.  Extension Members

   Problem type definitions MAY extend the problem details object with
   additional members that are specific to that problem type.

   For example, our out-of-credit problem above defines two such
   extensions -- "balance" and "accounts" to convey additional, problem-
   specific information.

   Similarly, the "validation error" example defines an "errors"
   extension that contains a list of individual error occurrences found,
   with details and a pointer to the location of each.

   Clients consuming problem details MUST ignore any such extensions
   that they don't recognize; this allows problem types to evolve and
   include additional information in the future.

   When creating extensions, problem type authors should choose their
   names carefully.  To be used in the XML format (see Appendix B), they
   will need to conform to the Name rule in Section 2.3 of [XML].

4.  Defining New Problem Types

   When an HTTP API needs to define a response that indicates an error
   condition, it might be appropriate to do so by defining a new problem
   type.

   Before doing so, it's important to understand what they are good for
   and what is better left to other mechanisms.

   Problem details are not a debugging tool for the underlying
   implementation; rather, they are a way to expose greater detail about
   the HTTP interface itself.  Designers of new problem types need to
   carefully take into account the Security Considerations (Section 5),
   in particular, the risk of exposing attack vectors by exposing
   implementation internals through error messages.

   Likewise, truly generic problems -- i.e., conditions that might apply
   to any resource on the Web -- are usually better expressed as plain
   status codes.  For example, a "write access disallowed" problem is
   probably unnecessary, since a 403 Forbidden status code in response
   to a PUT request is self-explanatory.

   Finally, an application might have a more appropriate way to carry an
   error in a format that it already defines.  Problem details are
   intended to avoid the necessity of establishing new "fault" or
   "error" document formats, not to replace existing domain-specific
   formats.

   That said, it is possible to add support for problem details to
   existing HTTP APIs using HTTP content negotiation (e.g., using the
   Accept request header to indicate a preference for this format; see
   [HTTP], Section 12.5.1).

   New problem type definitions MUST document:

```

