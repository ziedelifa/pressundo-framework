# Example — complete theme options page

```php
add_action('after_setup_theme', function (): void {
    pu_options_page([
        'id'          => 'acme-theme-options',
        'title'       => 'ACME Theme Options',
        'menu_title'  => 'Theme Options',
        'parent_slug' => 'themes.php',
        'storage_key' => 'acme_theme_options',
        'ui_theme'    => 'air',
        'sections'    => [
            pu_section('branding', [
                pu_media('logo', ['label' => 'Logo']),
                pu_color('brand_color', ['default' => '#2563EB']),
                pu_typography('heading_typography'),
            ]),
            pu_section('layout', [
                pu_button_group('layout_style', [
                    'wide'  => 'Wide',
                    'boxed' => 'Boxed',
                ], ['default' => 'wide']),
                pu_spacing('section_padding'),
                pu_background('page_background'),
            ]),
            pu_section('features', [
                pu_toggle('show_banner', ['label' => 'Show banner']),
                pu_text('banner_text', [
                    'show_if' => ['show_banner' => true],
                ]),
                pu_repeater('social_links', [
                    pu_text('label', ['required' => true]),
                    pu_url('url', ['required' => true]),
                ], ['max_rows' => 12]),
            ]),
        ],
    ]);
});
```

Read a value:

```php
$options = pu_options('acme_theme_options');
$brandColor = $options->get('brand_color', '#2563EB');
```
