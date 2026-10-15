# Pressundo Framework — Developer Documentation

Pressundo Framework is a modern WordPress developer framework with a stable Public API v1, modern theme-option and metabox UIs, progressive data migration, and a compatibility runtime for common Vafpress public APIs.

## Start here

- [Getting started](GETTING-STARTED.md)
- [Public API v1](PUBLIC-API.md)
- [Public API v1 freeze / RC contract](API-FREEZE.md)
- [Field reference](FIELDS.md)
- [Options pages](OPTIONS-PAGES.md)
- [Modern metaboxes](METABOXES.md)
- [Conditional logic](CONDITIONAL-LOGIC.md)
- [Progressive migration](PROGRESSIVE-MIGRATION.md)
- [Vafpress migration and compatibility](VAFPRESS-MIGRATION.md)
- [Security model](SECURITY.md)
- [Hooks and extensibility](HOOKS.md)

## Copy/paste examples

See the `examples/` directory for complete patterns that can be copied into a theme or plugin:

- `theme-options.md`
- `metabox.md`
- `legacy-vafpress.md`
- `mixed-legacy-modern.md`
- `progressive-migration.md`
- `custom-field-type.md`

## API stability

`pu_api_version()` returns `1`. Public `pu_*` helpers documented here are part of Public API v1. The 1.0.0 release-candidate line is feature-frozen. See `API-FREEZE.md` for the exact RC contract.
