# Modern options pages

Use `pu_options_page()` to register an options screen.

```php
pu_options_page([
    'id'          => 'acme-options',
    'title'       => 'ACME Theme Options',
    'menu_title'  => 'Theme Options',
    'parent_slug' => 'themes.php',
    'capability'  => 'edit_theme_options',
    'storage_key' => 'acme_theme_options',
    'ui_theme'    => 'air',
    'priority'    => 10,
    'description' => 'Global theme settings.',
    'sections'    => [
        pu_section('general', [
            pu_text('company_name'),
            pu_color('brand_color'),
        ]),
    ],
]);
```

## Configuration

| Key | Default | Purpose |
| --- | --- | --- |
| `id` | `pressundo-options` | menu/page identifier |
| `title` | `Pressundo Options` | page heading |
| `menu_title` | title | WordPress menu label |
| `parent_slug` | `themes.php` | parent admin menu; empty string creates a top-level menu |
| `capability` | `edit_theme_options` | required capability |
| `storage_key` | page ID | WordPress option containing the full settings array |
| `ui_theme` | `air` | `air`, `slate`, or `compact` |
| `sections` | `[]` | sections built with `pu_section()` |
| `priority` | `10` | admin menu registration priority |
| `icon_url` | dashicon default | top-level menu icon when `parent_slug` is empty |
| `position` | WordPress default | top-level menu position |

## Save behavior

- WordPress nonce verification.
- Capability verification.
- Per-field sanitization and server validation.
- All-or-nothing page save when validation fails.
- Concurrent-save revision detection prevents an older browser tab from silently overwriting newer settings.
- Presentation-only controls are never persisted.

## Read the values

```php
$options = pu_options('acme_theme_options');
$name = $options->get('company_name', '');
```

Because the page stores one associative option array, the namespace name should normally match `storage_key`.
