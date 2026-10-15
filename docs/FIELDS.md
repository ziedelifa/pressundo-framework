# Field reference

Pressundo registers 53 renderable field types. `group` and `repeater` are structural helpers handled by the schema renderer.

## Basic fields

| Helper | Stored value | Important arguments |
| --- | --- | --- |
| `pu_text()` | string | `placeholder`, `min_length`, `max_length`, `pattern` |
| `pu_textarea()` | string | `rows`, text validation arguments |
| `pu_number()` | number/string | `min`, `max`, `step` |
| `pu_email()` | string | valid email required when non-empty |
| `pu_url()` | string | HTTP/HTTPS URL policy |
| `pu_checkbox()` | bool | `required` requires checked state |
| `pu_toggle()` | bool | `required` requires enabled state |
| `pu_date()` | `YYYY-MM-DD` string | calendar validation |
| `pu_time()` | 24-hour time string | time validation |
| `pu_datetime()` | local datetime string | `YYYY-MM-DDTHH:MM[:SS]` |
| `pu_range()` | number/string | `min`, `max`, `step` |
| `pu_color()` | hex color | color picker; canonical hex storage |
| `pu_password()` | string | rendered as password input |
| `pu_hidden()` | string | hidden control |

## Choice fields

| Helper | Notes |
| --- | --- |
| `pu_select()` | single select; supports `searchable => true` |
| `pu_multiselect()` | multiple select; supports searchable UI |
| `pu_radio()` | radio choices |
| `pu_checkbox_group()` | multiple checkboxes |
| `pu_button_group()` | compact segmented choices |
| `pu_sortable()` | ordered selection with drag-and-drop |
| `pu_icon()` | icon identifier |

Options can be associative arrays:

```php
pu_select('layout', [
    'wide'  => 'Wide',
    'boxed' => 'Boxed',
]);
```

or descriptor rows:

```php
[
    ['value' => 'wide',  'label' => 'Wide'],
    ['value' => 'boxed', 'label' => 'Boxed'],
]
```

A callable can be used instead of a static array. Resolved options are cached for the current request unless `options_no_cache` is true.

## WordPress selectors

```php
pu_post_select('featured_page', [
    'post_type' => ['page'],
    'limit'     => 100,
]);

pu_term_select('category', ['taxonomy' => 'category']);
pu_user_select('owner', ['limit' => 100]);
pu_menu_select('menu');
pu_sidebar_select('sidebar');
pu_template_select('template');
```

`post_select`, `term_select`, `user_select` and `menu_select` store IDs. `sidebar_select` and `template_select` store their string identifiers. Supported selectors accept `multiple => true` where appropriate.

## Media/content fields

```text
pu_media       Attachment ID by default, or URL with return => url
pu_gallery     Deduplicated attachment IDs (or URLs when configured)
pu_editor      WordPress content editor style control
pu_code        Inert code text; normalized line endings
pu_json        JSON text with JSON validation
pu_css         Inert CSS text
pu_javascript  Inert JavaScript text
```

Code/CSS/JavaScript values are stored as text. The renderer never evaluates them.

## Structural fields

### Group

```php
pu_group('address', [
    pu_text('street'),
    pu_text('city'),
]);
```

Stored shape:

```php
[
    'street' => '...',
    'city'   => '...',
]
```

### Repeater

```php
pu_repeater('team', [
    pu_text('name', ['required' => true]),
    pu_text('role'),
], [
    'min_rows' => 1,
    'max_rows' => 12,
]);
```

Stored shape:

```php
[
    ['name' => 'Alex', 'role' => 'Design'],
    ['name' => 'Sam',  'role' => 'Development'],
]
```

The server has an additional hard safety limit of 250 rows by default.

## Typography

```php
pu_typography('heading_typography', [
    'default' => [
        'font_family'        => 'Inter',
        'font_weight'        => '700',
        'font_size'          => '42',
        'font_size_unit'     => 'px',
        'line_height'        => '1.15',
        'letter_spacing'     => '-0.6',
        'letter_spacing_unit'=> 'px',
        'text_transform'     => 'none',
        'text_decoration'    => 'none',
        'color'              => '#111827',
    ],
]);
```

Stored keys are exactly those shown above. Units are sanitized to the supported CSS unit set.

## Spacing, dimensions and radius

```php
pu_spacing('padding');
pu_dimensions('hero_dimensions');
pu_border_radius('card_radius');
```

`spacing` stores `top`, `right`, `bottom`, `left`, `unit`.

`dimensions` stores `width`, `height`, `min_width`, `max_width`, `min_height`, `max_height`, `unit`.

`border_radius` stores `top_left`, `top_right`, `bottom_right`, `bottom_left`, `unit`.

## Border

Stored keys:

```text
width
style: none|solid|dashed|dotted|double
color
unit
```

## Gradient

Stored keys:

```text
type: linear|radial
angle: 0..360
start_color
start_position: 0..100
end_color
end_position: 0..100
```

## Background

Stored keys:

```text
type: color|image|gradient
color
image (attachment ID)
position
size: auto|cover|contain
repeat: no-repeat|repeat|repeat-x|repeat-y
attachment: scroll|fixed
gradient (nested gradient structure)
```

## Box shadow

Stored keys:

```text
x
y
blur
spread
color
unit
inset
```

## Responsive value

```php
pu_responsive('heading_size', [
    'value_type'   => 'number',
    'default_unit' => 'px',
    'units'        => ['px', 'rem'],
]);
```

Stored shape:

```php
[
    'desktop' => '48',
    'tablet'  => '38',
    'mobile'  => '30',
    'unit'    => 'px',
]
```

`value_type` can be `number` or `text`.

## Key/value

`pu_key_value()` stores a list of:

```php
[
    ['key' => 'X-App',  'value' => 'Pressundo'],
    ['key' => 'X-Mode', 'value' => 'modern'],
]
```

## Presentation-only controls

`pu_heading()` and `pu_divider()` organize the admin UI and do not persist a value.
