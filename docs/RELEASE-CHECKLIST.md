# Release checklist — Free 1.0

This checklist records the Free 1.0 release criteria. The 1.0.0 stable runtime was promoted from the validated 1.0.0-rc.1 build without feature changes.

## Runtime

- [x] Public API version remains `1`.
- [x] Modern options page can render and save.
- [x] Modern metabox can render and save.
- [x] Air, Slate and Compact skins render the same schema.
- [x] Media assets are only loaded for schemas that require media.
- [x] Repeaters and sortable controls support drag-and-drop and keyboard-accessible actions.
- [x] Conditional logic works for initial render and live updates.

## Compatibility

- [x] Supported theme works after removing the old Vafpress runtime directory.
- [x] `VP_Option` and `VP_Metabox` retain existing data.
- [x] `vp_option()` and `vp_metabox()` reads pass.
- [x] 20/20 standard Vafpress field types remain automatic.
- [x] Legacy and modern fields can coexist in the same options screen.

## Security

- [ ] Plugin Check: 0 errors.
- [x] Arbitrary legacy callbacks remain disabled by default.
- [x] Unsafe legacy template include remains disabled by default.
- [x] URL/media URL policy remains HTTP/HTTPS only.
- [x] Concurrent save protection passes for options and metaboxes.
- [x] Unknown field fallback sanitization passes.
- [x] Repeater hard limit passes.

## Regression suites

- [x] Free Fields: 18/18.
- [x] Advanced Theme Fields: 14/14.
- [x] Vafpress Compatibility Audit: 18/18.
- [x] Security & CI: 17/17.
- [x] Release Readiness suite: all green.

## Packaging

- [x] PHP lint passes for every distributed PHP file.
- [x] Admin JavaScript parses successfully.
- [x] No `.github/`, `tests/`, PHPUnit, PHPCS, Composer or Node development manifests in the WordPress.org ZIP.
- [x] `readme.txt` stable tag matches plugin header version.
- [x] `Tested up to` uses WordPress major.minor format.
- [x] Documentation and Markdown examples contain no executable PHP files.
- [x] ZIP root contains one `pressundo-framework/` directory.

## Release policy

Pressundo does not delete application options or post meta on uninstall. Those values belong to the theme/plugin using the framework and may be required if that application is reactivated or rolled back.
