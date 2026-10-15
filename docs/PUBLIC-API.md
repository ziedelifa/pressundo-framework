# Public API v1

`pu_api_version()` returns the public API version. In Pressundo 0.16 it returns `1`.

## Value access

```php
pu_get(string $key, mixed $default = null, array $args = []): mixed
pu_set(string $key, mixed $value, array $args = []): bool
pu_delete(string $key, array $args = []): bool
pu_exists(string $key, array $args = []): bool

pu_options(string $name, array $config = []): ValueNamespace
pu_meta(int $postId, string $name, array $config = []): ValueNamespace
```

`ValueNamespace` provides `get()`, `set()`, `delete()`, `exists()`, `name()` and `config()`.

## UI registration

```php
pu_options_page(array $config): Pressundo\Framework\Admin\OptionsPage
pu_metabox(array $config): Pressundo\Framework\Admin\Metabox
```

`pu_options_page()` registers a modern WordPress admin options page. `pu_metabox()` registers a modern post metabox. They use the same renderer, field types, validation, conditional logic and UI themes.

## Schema helpers

Core:

```text
pu_field
pu_text
pu_textarea
pu_number
pu_email
pu_url
pu_select
pu_multiselect
pu_radio
pu_checkbox
pu_checkbox_group
pu_toggle
pu_color
pu_date
pu_time
pu_datetime
pu_range
pu_media
pu_gallery
pu_editor
pu_code
pu_icon
pu_sortable
pu_group
pu_repeater
pu_section
```

Advanced theme fields:

```text
pu_font_family
pu_font_weight
pu_unit
pu_typography
pu_spacing
pu_dimensions
pu_background
pu_gradient
pu_border
pu_border_radius
pu_box_shadow
pu_responsive
pu_button_group
pu_post_select
pu_term_select
pu_user_select
pu_menu_select
pu_sidebar_select
pu_template_select
pu_key_value
pu_hidden
pu_password
pu_json
pu_css
pu_javascript
pu_heading
pu_divider
```

## Field utilities

```php
pu_resolve_options(array $field): array
pu_field_visible(array $field, array $values): bool
pu_sanitize_field(array $field, mixed $value): mixed
pu_validate_field(array $field, mixed $value): ValidationResult
```

`ValidationResult` exposes the sanitized value, validation state and errors.

## Custom field types

```php
pu_register_field_type(FieldTypeInterface $field): void
pu_has_field_type(string $type): bool
pu_field_types(): array
```

A custom type implements:

```php
interface FieldTypeInterface
{
    public function type(): string;
    public function sanitize(mixed $value, array $field): mixed;
    public function render(array $field, mixed $value): string;
}
```

## Common field arguments

All schema fields support these common keys:

```text
label
placeholder
description
default
required
disabled
readonly
show_if
hide_if
condition_relation
sanitize / sanitize_callback
validation_message
required_message
```

Text-like fields may also use `min_length`, `max_length` and `pattern`. Number/range fields support `min`, `max` and `step`. Selection fields support dynamic `options`, `options_callback` and `options_no_cache`.

See [Field reference](FIELDS.md).
