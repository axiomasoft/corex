# corex/panels

Headless, read-only panel descriptors for CoreX modules. Applications supply tagged `PanelProvider` implementations; `PanelCatalogue` reads current module and entity state for every request and fails closed on unavailable policy.

The package exposes no routes, UI components, persistent registry, command execution, or cached grants.
