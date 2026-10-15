# Public API v1 freeze — 1.0.0

This document records the frozen Pressundo Framework Free Public API v1 surface for the 1.0.x stable release line.

## Release contract

- `PRESSUNDO_FRAMEWORK_VERSION`: `1.0.0`
- `pu_api_version()`: `1`
- The `pu_*` helper signatures listed below are frozen for 1.0 stable.
- Additive helpers may be introduced in future 1.x releases without breaking existing signatures.
- Breaking public API changes require a future API version increment.

## Public `pu_*` helper signatures

- `pu_get(string $key, mixed $default = null, array $args = []): mixed`
- `pu_set(string $key, mixed $value, array $args = []): bool`
- `pu_delete(string $key, array $args = []): bool`
- `pu_exists(string $key, array $args = []): bool`
- `pu_options(string $name, array $config = []): ValueNamespace`
- `pu_meta(int $postId, string $name, array $config = []): ValueNamespace`
- `pu_api_version(): string`
- `pu_field(string $type, string $id, array $args = []): array`
- `pu_text(string $id, array $args = []): array`
- `pu_textarea(string $id, array $args = []): array`
- `pu_select(string $id, array|callable $options = [], array $args = []): array`
- `pu_checkbox(string $id, array $args = []): array`
- `pu_toggle(string $id, array $args = []): array`
- `pu_group(string $id, array $fields, array $args = []): array`
- `pu_repeater(string $id, array $fields, array $args = []): array`
- `pu_section(string $id, array $fields, array $args = []): array`
- `pu_register_field_type(\Pressundo\Framework\Contracts\FieldTypeInterface $field): void`
- `pu_has_field_type(string $type): bool`
- `pu_field_types(): array`
- `pu_sanitize_field(array $field, mixed $value): mixed`
- `pu_validate_field(array $field, mixed $value): \Pressundo\Framework\Api\ValidationResult`
- `pu_number(string $id, array $args = []): array`
- `pu_email(string $id, array $args = []): array`
- `pu_url(string $id, array $args = []): array`
- `pu_radio(string $id, array|callable $options = [], array $args = []): array`
- `pu_multiselect(string $id, array|callable $options = [], array $args = []): array`
- `pu_checkbox_group(string $id, array|callable $options = [], array $args = []): array`
- `pu_color(string $id, array $args = []): array`
- `pu_date(string $id, array $args = []): array`
- `pu_time(string $id, array $args = []): array`
- `pu_range(string $id, array $args = []): array`
- `pu_media(string $id, array $args = []): array`
- `pu_gallery(string $id, array $args = []): array`
- `pu_editor(string $id, array $args = []): array`
- `pu_code(string $id, array $args = []): array`
- `pu_icon(string $id, array $args = []): array`
- `pu_sortable(string $id, array|callable $options = [], array $args = []): array`
- `pu_resolve_options(array $field): array`
- `pu_field_visible(array $field, array $values): bool`
- `pu_options_page(array $config): \Pressundo\Framework\Admin\OptionsPage`
- `pu_metabox(array $config): \Pressundo\Framework\Admin\Metabox`
- `pu_font_family(string $id, array $args = []): array`
- `pu_font_weight(string $id, array $args = []): array`
- `pu_unit(string $id, array $args = []): array`
- `pu_typography(string $id, array $args = []): array`
- `pu_spacing(string $id, array $args = []): array`
- `pu_dimensions(string $id, array $args = []): array`
- `pu_background(string $id, array $args = []): array`
- `pu_gradient(string $id, array $args = []): array`
- `pu_border(string $id, array $args = []): array`
- `pu_border_radius(string $id, array $args = []): array`
- `pu_box_shadow(string $id, array $args = []): array`
- `pu_responsive(string $id, array $args = []): array`
- `pu_button_group(string $id, array $options = [], array $args = []): array`
- `pu_datetime(string $id, array $args = []): array`
- `pu_post_select(string $id, array $args = []): array`
- `pu_term_select(string $id, array $args = []): array`
- `pu_user_select(string $id, array $args = []): array`
- `pu_menu_select(string $id, array $args = []): array`
- `pu_sidebar_select(string $id, array $args = []): array`
- `pu_template_select(string $id, array $args = []): array`
- `pu_key_value(string $id, array $args = []): array`
- `pu_hidden(string $id, array $args = []): array`
- `pu_password(string $id, array $args = []): array`
- `pu_json(string $id, array $args = []): array`
- `pu_css(string $id, array $args = []): array`
- `pu_javascript(string $id, array $args = []): array`
- `pu_heading(string $id, string $text, array $args = []): array`
- `pu_divider(string $id, array $args = []): array`

## Compatibility surface frozen for 1.0

- `VP_Option`
- `VP_Metabox`
- `VP_Security`
- `VP_Option_Control_Set`
- `VP_Site_GoogleWebFont`
- safe `VP_ShortcodeGenerator` facade
- `vp_option()`
- `vp_metabox()`
- supported Vafpress datasource helpers
- 20/20 standard Vafpress field types in the compatibility matrix

Unsafe legacy arbitrary PHP callback execution and unsafe template include fallback are **not** part of the default compatibility contract. They remain disabled unless explicitly opted in by the site owner.

## UI and storage contracts

- Options pages and modern metaboxes use the same schema renderer.
- `air`, `slate`, and `compact` remain supported UI themes.
- Progressive migration strategies remain `preserve`, `lazy`, and `copy`.
- Application options and post meta are not deleted on uninstall.

## Stable policy

The `1.0.0` stable runtime and Public API v1 were promoted from the validated `1.0.0-rc.1` release candidate without feature changes. Future 1.x releases may add compatible helpers and fixes, but breaking public API changes require a future API version increment.
