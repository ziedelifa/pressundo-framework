# Example — modern page metabox

```php
add_action('after_setup_theme', function (): void {
    pu_metabox([
        'id'          => 'acme-hero-settings',
        'title'       => 'Hero Settings',
        'post_types'  => ['page'],
        'storage_key' => '_acme_hero_settings',
        'ui_theme'    => 'compact',
        'sections'    => [
            pu_section('hero', [
                pu_toggle('enabled', [
                    'label'   => 'Enable custom hero',
                    'default' => false,
                ]),
                pu_text('title', [
                    'show_if' => ['enabled' => true],
                ]),
                pu_media('image', [
                    'show_if' => ['enabled' => true],
                ]),
                pu_spacing('padding', [
                    'show_if' => ['enabled' => true],
                ]),
            ]),
        ],
    ]);
});
```

Front-end access:

```php
$hero = pu_meta(get_the_ID(), '_acme_hero_settings');

if ($hero->get('enabled', false)) {
    $title = $hero->get('title', get_the_title());
}
```
