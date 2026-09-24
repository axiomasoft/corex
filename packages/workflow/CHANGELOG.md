# Changelog

## Unreleased

- Pin workflow trigger intents to definition id and version inside the existing payload. Registry fingerprint is stored provenance and is excluded from the replay hash. Legacy non-empty refs keep the `engine-binding/1` comparison and are not rewritten.
- Add the initial `engine-binding/1` ports and durable intent store.
