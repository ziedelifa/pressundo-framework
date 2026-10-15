=== Pressundo Framework ===
Contributors: pressundo
Tags: framework, theme-options, custom-fields, developer-tools, migration
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Modern, fast and flexible WordPress developer framework with theme options, metaboxes, Vafpress compatibility and progressive data migration.

== Description ==

Pressundo Framework provides a modern developer API for WordPress options, metaboxes, advanced fields and progressive migrations while maintaining a safe compatibility path for supported Vafpress projects.

Highlights:

* Stable Public API v1 with `pu_*` helpers.
* Modern options pages and modern metaboxes using the same schema engine.
* Air, Slate and Compact admin UI themes.
* 53 registered field types plus structural groups and repeaters.
* Typography, spacing, background, responsive values, media, sortable controls and WordPress data selectors.
* Conditional logic with indexed live evaluation.
* Progressive preserve, lazy and copy migration strategies.
* Compatibility runtime for common public Vafpress APIs without requiring the obsolete Vafpress runtime.
* Legacy arbitrary PHP callbacks and unsafe template includes are disabled by default.
* Server-side validation, bounded repeater input and concurrent-save detection.
* Developer documentation and copy/paste examples included in `docs/` and `examples/`.

== Installation ==

1. Upload the `pressundo-framework` folder to `/wp-content/plugins/`.
2. Activate Pressundo Framework from the Plugins screen.
3. Use the `pu_*` API in your theme or plugin, or keep supported Vafpress public APIs during progressive migration.

== Frequently Asked Questions ==

= Do I need the old Vafpress framework installed? =

No for the supported public compatibility surface. Pressundo provides compatibility for common `VP_Option`, `VP_Metabox`, `vp_option()` and related APIs.

= Can I create new metaboxes without Vafpress? =

Yes. Public API v1 includes `pu_metabox()` for modern metaboxes using the same fields, validation, conditional logic and Air/Slate/Compact UI themes as modern options pages.

= Does compatibility execute arbitrary legacy PHP callbacks? =

No. Arbitrary whitelisted Vafpress callbacks and unsafe legacy template includes are disabled by default. Explicit developer opt-ins are available only for exceptional migrations.

= Can legacy and modern fields coexist? =

Yes. A Vafpress-compatible options page can keep legacy fields while new Pressundo fields are added progressively and rendered with the same Pressundo design system.

= Does uninstall remove theme or post settings? =

No. Pressundo does not claim ownership of application options or post meta created for a theme/plugin, so it does not automatically delete those values.

= Where is the developer documentation? =

The plugin package includes `docs/INDEX.md` and copy/paste patterns in the `examples/` directory.

== Changelog ==

= 1.0.0 =

* First stable Pressundo Framework Free release.
* Public API v1 promoted unchanged from the validated 1.0.0-rc.1 contract.
* Includes modern options pages and metaboxes, 53 field types, Air/Slate/Compact UI themes and conditional logic.
* Includes progressive preserve/lazy/copy migration and the validated Vafpress public compatibility runtime.
* Includes security hardening, concurrent-save protection, developer documentation and WordPress.org-ready packaging.
* No application options or post meta are deleted on uninstall.

= 1.0.0-rc.1 =

* First Pressundo Framework Free 1.0 release candidate.
* Feature freeze: Public API v1 is frozen for the 1.0 stable release.
* Includes modern options pages and metaboxes, 53 field types, Air/Slate/Compact UI themes and conditional logic.
* Includes progressive preserve/lazy/copy migration and the validated Vafpress public compatibility runtime.
* Includes security hardening, concurrent-save protection, developer documentation and release packaging.
* No application options or post meta are deleted on uninstall.

= 0.16.0-dev =

* Added modern Public API v1 `pu_metabox()` registration.
* Added complete developer documentation for API v1, fields, options, metaboxes, conditional logic, migration, Vafpress compatibility, security and hooks.
* Added copy/paste theme options, metabox, Vafpress, mixed migration, progressive migration and custom-field examples.
* Added final Free 1.0 release checklist and packaging documentation.
* Public API version remains 1.

= 0.15.3-dev =

* WordPress.org Plugin Check hardening completed with zero reported errors in the validated package.
* Production package separation from test and CI source files.
* Improved escaping, request sanitization and internationalization comments.

= 0.15.1-dev =

* Hardened unknown-field fallback sanitization.
* Security and concurrent-save regression fixes.

== Upgrade Notice ==

= 1.0.0 =

First stable release of Pressundo Framework Free. Public API v1 is unchanged from the validated 1.0.0-rc.1 release candidate.
