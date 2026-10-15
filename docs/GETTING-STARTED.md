# Getting started

## Requirements

- WordPress 6.7 or newer
- PHP 8.2 or newer

Activate **Pressundo Framework** before using the API from a theme or plugin.

## Create a modern options page

```php
add_action('after_setup_theme', function (): void {
    pu_options_page([
        'id'          => 'acme-theme-options',
        'title'       => 'Theme Options',
        'menu_title'  => 'Theme Options',
        'parent_slug' => 'themes.php',
        'capability'  => 'edit_theme_options',
        'storage_key' => 'acme_theme_options',
        'ui_theme'    => 'air',
        'sections'    => [
            pu_section('general', [
                pu_text('site_tagline', [
                    'label'   => 'Site tagline',
                    'default' => 'Built with Pressundo',
                ]),
                pu_toggle('show_banner', [
                    'label'   => 'Show announcement banner',
                    'default' => true,
                ]),
                pu_color('brand_color', [
                    'label'   => 'Brand color',
                    'default' => '#2563EB',
                ]),
            ]),
        ],
    ]);
});
```

The page is saved as one associative array in `acme_theme_options`.

## Read values

For a namespaced option array:

```php
$options = pu_options('acme_theme_options');
$tagline = $options->get('site_tagline', 'Default tagline');
$color   = $options->get('brand_color', '#2563EB');
```

You can also use the low-level storage helpers when a namespace is not needed:

```php
$value = pu_get('acme_single_option', 'fallback');
pu_set('acme_single_option', 'value');
pu_delete('acme_single_option');
```

## Create a modern metabox

```php
add_action('after_setup_theme', function (): void {
    pu_metabox([
        'id'          => 'acme-page-settings',
        'title'       => 'Page Settings',
        'post_types'  => ['page'],
        'storage_key' => '_acme_page_settings',
        'ui_theme'    => 'air',
        'sections'    => [
            pu_section('hero', [
                pu_toggle('hero_enabled', ['label' => 'Enable hero']),
                pu_text('hero_title', [
                    'label'   => 'Hero title',
                    'show_if' => ['hero_enabled' => true],
                ]),
            ]),
        ],
    ]);
});
```

Read the stored metabox values with:

```php
$page = pu_meta(get_the_ID(), '_acme_page_settings');
$title = $page->get('hero_title', '');
```

## UI themes

Options pages and modern metaboxes accept:

```php
'ui_theme' => 'air'     // spacious, default
'ui_theme' => 'slate'   // higher contrast
'ui_theme' => 'compact' // dense administration screens
```

The stored data is independent of the selected UI theme.

## Legacy Vafpress

For supported public Vafpress APIs, the obsolete Vafpress runtime is not required. Existing `VP_Option`, `VP_Metabox`, `vp_option()` and `vp_metabox()` calls can remain while code is migrated progressively.

See [Vafpress migration](VAFPRESS-MIGRATION.md).
