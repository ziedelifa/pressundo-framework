# Example — keep a supported Vafpress options page

The old Vafpress framework folder is not required for the supported public compatibility surface.

```php
$themeOptions = new VP_Option([
    'is_dev_mode' => false,
    'option_key'  => 'legacy_theme_options',
    'page_slug'   => 'legacy-theme-options',
    'menu_page'   => 'themes.php',
    'use_auto_group_naming' => true,
    'template'    => get_template_directory() . '/admin/options.php',
]);
```

Existing reads can remain temporarily:

```php
$title = vp_option('legacy_theme_options.site_title');
```

Migrate one consumer at a time to a modern namespace while preserving the same option container when appropriate.
