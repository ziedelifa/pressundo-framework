# Pressundo Framework

**A modern WordPress options & metabox framework with 53 field types, progressive Vafpress migration, and a stable developer API.**

Pressundo Framework is a modern, fast and flexible framework for building **WordPress options pages, metaboxes and advanced custom fields**.

It can also act as a **modern replacement for Vafpress Framework**, allowing existing projects to migrate progressively without forcing an immediate rewrite or destructive data migration.

> Build new WordPress admin interfaces with the modern `pu_*` API, keep supported Vafpress APIs temporarily, and migrate at your own pace.

---

## Why Pressundo?

Many WordPress themes and plugins still depend on old options and metabox frameworks that are difficult to maintain on modern WordPress and PHP versions.

Pressundo provides a modern alternative focused on:

- **Modern WordPress development**
- **Options pages**
- **Metaboxes**
- **53 field types**
- **Progressive Vafpress migration**
- **Backward compatibility**
- **Security by default**
- **No destructive migration**
- **Stable Public API v1**

---

## Highlights

- 53 registered field types
- Modern Options Pages
- Modern Metaboxes
- Stable Public API v1
- Air, Slate and Compact admin UI themes
- Conditional logic with live indexed evaluation
- Repeaters and sortable controls
- Typography and responsive fields
- WordPress post, term, user, menu and sidebar selectors
- Media and gallery fields
- Progressive migration strategies
- Vafpress compatibility layer
- Server-side validation and sanitization
- Concurrent-save protection
- Developer documentation and examples
- No automatic deletion of application data on uninstall

---

## Quick Start

Register a modern options page:

```php
pu_options_page([
    'id'         => 'theme-options',
    'title'      => 'Theme Options',
    'menu_title' => 'Theme Options',
    'ui_theme'   => 'air',

    'sections' => [
        [
            'id'    => 'general',
            'title' => 'General',

            'fields' => [
                pu_text('site_title', [
                    'label' => 'Site title',
                ]),

                pu_color('brand_color', [
                    'label'   => 'Brand color',
                    'default' => '#2563EB',
                ]),

                pu_toggle('sticky_header', [
                    'label' => 'Sticky header',
                ]),
            ],
        ],
    ],
]);
```

Read a value:

```php
$options = pu_options('theme-options');

$brand_color = $options->get('brand_color', '#2563EB');
```

---

## Modern Metaboxes

Pressundo uses the same field system for options pages and metaboxes.

```php
pu_metabox([
    'id'          => 'page-settings',
    'title'       => 'Page Settings',
    'post_types'  => ['page'],
    'storage_key' => '_page_settings',
    'ui_theme'    => 'air',

    'sections' => [
        [
            'id'    => 'general',
            'title' => 'General',

            'fields' => [
                pu_toggle('hide_title', [
                    'label' => 'Hide page title',
                ]),
            ],
        ],
    ],
]);
```

Read metabox data:

```php
$settings = pu_meta(get_the_ID(), '_page_settings');

$hide_title = $settings->get('hide_title', false);
```

---

# Vafpress Framework Alternative

Pressundo was designed to provide a practical migration path for projects that still depend on **Vafpress Framework**.

You do not need to rewrite an entire theme or plugin in one operation.

Pressundo provides compatibility for common public Vafpress APIs including:

```php
VP_Option
VP_Metabox
VP_Security
VP_Option_Control_Set
VP_Site_GoogleWebFont

vp_option()
vp_metabox()
```

Common Vafpress field types and helpers are also supported.

---

## Progressive Vafpress Migration

The old Vafpress runtime can be removed while temporarily keeping existing application code.

Typical migration:

```text
Install Pressundo
        ↓
Remove vafpress-framework/
        ↓
Remove the old Vafpress bootstrap
        ↓
Keep existing VP_Option / VP_Metabox definitions
        ↓
Pressundo reads the existing data
        ↓
Add modern Pressundo fields
        ↓
Migrate progressively to pu_*
```

Pressundo supports three migration strategies:

| Strategy | Behavior |
|---|---|
| `preserve` | Legacy storage remains the source of truth |
| `lazy` | Legacy values are copied when first accessed |
| `copy` | Modern storage is used while legacy data remains synchronized |

There is no destructive `move` strategy in Pressundo Free.

---

## Legacy + Modern Fields

Existing Vafpress-compatible fields and new Pressundo fields can coexist.

```text
Legacy textbox
Legacy select
Legacy repeater

        +

Modern typography
Modern spacing
Modern searchable select
Modern responsive controls
```

This allows large WordPress projects to modernize incrementally instead of performing a risky full rewrite.

---

## 53 Field Types

Pressundo includes fields for common settings as well as advanced theme development.

### Basic

- Text
- Textarea
- Number
- Email
- URL
- Password
- Hidden
- Checkbox
- Toggle
- Color
- Date
- Time
- Datetime
- Range

### Choices

- Select
- Searchable Select
- Multi-select
- Radio
- Checkbox Group
- Button Group

### Media & Content

- Media
- Gallery
- Editor
- Code
- JSON
- CSS
- JavaScript
- Icon

### Layout & Data

- Sortable
- Group
- Repeater
- HTML
- Notice
- Heading
- Divider

### Advanced Theme Fields

- Font Family
- Font Weight
- Typography
- Unit
- Spacing
- Dimensions
- Border
- Border Radius
- Background
- Gradient
- Box Shadow
- Responsive
- Post Selector
- Term Selector
- User Selector
- Menu Selector
- Sidebar Selector
- Template Selector
- Key / Value

---

## Admin UI

Pressundo includes three interface themes.

### Air

Clean modern interface for most projects.

### Slate

More structured visual hierarchy for complex configuration screens.

### Compact

Reduced spacing for dense administration interfaces.

All three themes use the same schema and field engine.

---

## Conditional Logic

Fields can react to other field values without requiring a full page reload.

Pressundo supports conditions such as:

```text
=
==
!=
<>
in
not_in
>
>=
<
<=
empty
not_empty
truthy
falsy
contains
not_contains
```

Dependencies are indexed client-side so changing one field only reevaluates fields that depend on it.

---

## Security

Pressundo follows a secure-by-default approach.

Included protections:

- nonce validation
- capability checks
- output escaping
- server-side sanitization
- server-side validation
- HTTP/HTTPS URL restrictions
- protection against unsafe URL schemes
- bounded repeater input
- recursive fallback sanitization
- filtered HTML
- concurrent-save detection

Code, CSS and JavaScript fields are stored as text.

Pressundo does **not** evaluate their contents.

Legacy arbitrary PHP callbacks and unsafe template includes are disabled by default.

---

## Public API

Pressundo Free 1.0 uses **Public API version 1**.

Core helpers include:

```php
pu_options_page()
pu_metabox()

pu_options()
pu_meta()

pu_get()
pu_set()
pu_exists()
pu_delete()

pu_field()
pu_validate_field()
pu_sanitize_field()
pu_resolve_options()
pu_field_visible()

pu_register_field_type()
pu_has_field_type()
pu_field_types()
```

The Public API v1 contract is frozen for the 1.x release line.

---

## Requirements

- WordPress 6.7+
- PHP 8.2+
- Modern WordPress installation

Tested up to:

```text
WordPress 7.1
```

---

## Installation

Download the latest Pressundo Framework release.

Copy:

```text
pressundo-framework/
```

to:

```text
/wp-content/plugins/
```

Then activate **Pressundo Framework** from the WordPress Plugins screen.

---

## Documentation

Full documentation:

**https://docs.pressundo.com**

The repository also contains:

```text
docs/
examples/
```

with API documentation and copy/paste implementation examples.

---

## Documentation Topics

The documentation covers:

- Getting Started
- Public API
- Fields
- Options Pages
- Metaboxes
- Conditional Logic
- Progressive Migration
- Vafpress Migration
- Security
- Hooks
- Custom Field Types

---

## Examples

Included examples cover:

```text
Theme Options
Modern Metaboxes
Legacy Vafpress
Mixed Legacy + Modern
Progressive Migration
Custom Field Types
```

---

## Who Is Pressundo For?

Pressundo is designed for:

- WordPress plugin developers
- WordPress theme developers
- agencies maintaining custom WordPress projects
- developers replacing Vafpress Framework
- developers building advanced WordPress administration interfaces
- projects requiring reusable options and metabox schemas

---

## Vafpress Developers

If your project currently contains:

```php
new VP_Option(...)
new VP_Metabox(...)
vp_option(...)
vp_metabox(...)
```

Pressundo provides a migration path that lets you modernize the project progressively instead of rewriting everything immediately.

See:

**https://docs.pressundo.com**

for the complete Vafpress migration guide.

---

## Data Ownership

Pressundo does not claim ownership of settings created by your theme or plugin.

Uninstalling Pressundo therefore does **not** automatically delete application options or post metadata.

This prevents accidental data loss.

---

## Version

Current stable release:

```text
Pressundo Framework 1.0.0
Public API: 1
```

---

## License

Pressundo Framework is licensed under:

**GPL-2.0-or-later**

---

## Contributing

Bug reports, compatibility reports and focused pull requests are welcome.

When reporting an issue, please include:

- WordPress version
- PHP version
- Pressundo version
- minimal reproduction
- relevant configuration/schema

---

## Support Pressundo

If Pressundo saves you development time or helps you modernize a legacy WordPress project, consider giving the repository a ⭐.

It helps other WordPress developers discover the project.

---

**Pressundo Framework**

Modern WordPress options, metaboxes and Vafpress migration.
