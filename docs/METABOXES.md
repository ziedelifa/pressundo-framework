# Modern metaboxes

Pressundo 0.16 adds the Public API v1 helper `pu_metabox()` for modern post metaboxes.

```php
pu_metabox([
    'id'          => 'acme-page-options',
    'title'       => 'Page Options',
    'post_types'  => ['page'],
    'context'     => 'normal',
    'priority'    => 'high',
    'storage_key' => '_acme_page_options',
    'ui_theme'    => 'air',
    'sections'    => [
        pu_section('hero', [
            pu_toggle('enabled', ['label' => 'Enable hero']),
            pu_text('title', [
                'show_if' => ['enabled' => true],
            ]),
            pu_media('image'),
        ]),
    ],
]);
```

`post_type` can be used as a singular alias for `post_types`.

## Configuration

| Key | Default | Purpose |
| --- | --- | --- |
| `id` | `pressundo-metabox` | unique metabox ID |
| `title` | `Pressundo Metabox` | metabox title |
| `post_types` | `['post']` | target post types |
| `context` | `normal` | WordPress metabox context |
| `priority` | `high` | WordPress metabox priority |
| `storage_key` | `_' . id` | single post-meta key containing the settings array |
| `ui_theme` | `air` | `air`, `slate`, or `compact` |
| `sections` | `[]` | sectioned schema |
| `fields` | `[]` | optional flat schema when sections are not needed |

## Save behavior

The modern metabox uses:

- nonce verification;
- `edit_post` capability checks;
- autosave/revision guards;
- the central field sanitizer and validator;
- concurrent revision detection;
- all-or-nothing saves when validation fails.

Hooks are emitted on conflict, validation failure and successful save. See [Hooks](HOOKS.md).

## Read values

```php
$meta = pu_meta($postId, '_acme_page_options');
$enabled = $meta->get('enabled', false);
$title   = $meta->get('title', '');
```

## Legacy metaboxes

Existing supported `VP_Metabox` declarations can continue to run through the compatibility runtime without the old Vafpress framework folder. New projects should prefer `pu_metabox()`.
