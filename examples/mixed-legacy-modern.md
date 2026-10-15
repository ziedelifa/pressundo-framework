# Example — legacy and modern fields together

A legacy-compatible `VP_Option` page can retain its converted Vafpress schema while adding Pressundo-native sections. The compatibility adapter uses the same Pressundo renderer and UI skin.

Conceptually:

```php
new VP_Option([
    'option_key' => 'legacy_theme_options',
    'page_slug'  => 'legacy-theme-options',
    'template'   => get_template_directory() . '/admin/options.php',
    'ui_theme'   => 'air',

    'pressundo_sections' => [
        pu_section('new-design-controls', [
            pu_typography('heading_typography'),
            pu_spacing('section_spacing'),
            pu_background('page_background'),
        ]),
    ],
]);
```

This allows a developer to keep working legacy fields while new work uses the modern API. Stored legacy data is preserved rather than forcing a site-by-site migration.
