# Hooks and extensibility

## Framework and fields

```text
pressundo_framework_loaded
pressundo_framework_register_fields
pressundo_field_type_registered
pressundo_field_definition
pressundo_field_definition_{$type}
pressundo_section_definition
pressundo_field_sanitized
pressundo_field_sanitized_{$type}
pressundo_field_validation_errors
pressundo_field_options
pressundo_field_options_{$type}
pressundo_dynamic_options_error
```

## Modern metaboxes

```text
pressundo_metabox_save_conflict
pressundo_metabox_validation_failed
pressundo_metabox_saved
```

## Progressive migration

```text
pressundo_progressive_migration_from_legacy
pressundo_progressive_migration_to_legacy
pressundo_progressive_value_migrated
pressundo_progressive_value_migration_failed
```

## Security limits

```text
pressundo_security_max_repeater_rows
pressundo_security_max_input_items
```

## Vafpress compatibility

Pressundo also emits compatibility hooks used by the Vafpress facade, including `pressundo_vafpress_metabox_saved`, `pressundo_vp_metabox_save_conflict` and legacy `vp_option_*` lifecycle hooks where supported.

## Example: modify every text field definition

```php
add_filter('pressundo_field_definition_text', function (array $field): array {
    $field['max_length'] ??= 200;
    return $field;
});
```

## Example: custom field type

See `examples/custom-field-type.md`.
