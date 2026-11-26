# Rejected proposals

Review these decisions when preparing a major release. Each row identifies the evidence that would justify reconsidering the proposal.

| Proposal | Reason | Reconsider when |
|---|---|---|
| Add `packages/capabilities/` and `packages/integrations/` directories | Moving 20 path repositories would require changes to CI, analysis and publishing. The architecture gate already enforces dependency direction. | The number of packages grows substantially. |
| Split nine kernel capabilities into packages immediately | Each split would require a name, mirror, changelog, CI lane, versioning and compatibility checks for one or two files. Existing architecture tests enforce the boundaries. | A capability needs independent releases, has a consumer outside the kernel, or introduces a heavy dependency. |
| Rename `Extend` to `Extension(s)` and `Gate` to `Activation` | The established names identify a closed set of extension mechanisms. Changing them would impose a migration on consumers. | An RFC changes the extension mechanisms themselves. |
| Use `YYYY-MM` as the primary external API compatibility signal | Self-hosted clients and servers update independently. Clients need to discover installed capabilities. | The product centrally controls deployment. |
| Split `operations-http` and `operations-tools` | No measured dependency cost currently justifies separate packages. | Some installations need to avoid a substantial transport dependency. |
| Release an external API version quarterly | A calendar cadence only served the date-based versioning proposal. | The project adopts date-based versions. |
